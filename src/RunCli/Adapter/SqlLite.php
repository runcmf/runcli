<?php
/**
 * Copyright 2016 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


namespace RunCli\Adapter;

use Illuminate\Database\Capsule\Manager as DB;

class SqlLite extends Common implements AdapterInterface
{
  protected $doctrineTypeMapping = array(
            'boolean'          => 'boolean',
            'tinyint'          => 'boolean',
            'smallint'         => 'smallint',
            'mediumint'        => 'integer',
            'int'              => 'integer',
            'integer'          => 'integer',
            'serial'           => 'integer',
            'bigint'           => 'bigint',
            'bigserial'        => 'bigint',
            'clob'             => 'text',
            'tinytext'         => 'text',
            'mediumtext'       => 'text',
            'longtext'         => 'text',
            'text'             => 'text',
            'varchar'          => 'string',
            'longvarchar'      => 'string',
            'varchar2'         => 'string',
            'nvarchar'         => 'string',
            'image'            => 'string',
            'ntext'            => 'string',
            'char'             => 'string',
            'date'             => 'date',
            'datetime'         => 'datetime',
            'timestamp'        => 'datetime',
            'time'             => 'time',
            'float'            => 'float',
            'double'           => 'float',
            'double precision' => 'float',
            'real'             => 'float',
            'decimal'          => 'decimal',
            'numeric'          => 'decimal',
            'blob'             => 'blob',
        );

  public function listTableNames($database)
  {
    $q = "SELECT name FROM sqlite_master 
        WHERE type = 'table' 
        AND name != 'sqlite_sequence' 
        AND name != 'geometry_columns' 
        AND name != 'spatial_ref_sys'
         UNION ALL SELECT name FROM sqlite_temp_master 
        WHERE type = 'table' ORDER BY name";
  }

  public function getEnum($table, $database)
  {

  }

  public function listTableColumns($table, $database)
  {
    $table = str_replace('.', '__', $table);

    $q = "PRAGMA table_info('$table')";
  }

  public function listTableIndexes($table, $database)
  {
    $table = str_replace('.', '__', $table);

    $q = "PRAGMA index_list('$table')";
  }

  public function listTableForeignKeys($table, $database)
  {
    $table = str_replace('.', '__', $table);

    $q = "PRAGMA foreign_key_list('$table')";
  }

  public function createDatabase($schemaName, $charset, $collation, $cfg)
  {
    //$dbh = new PDO("sqlite:/path/to/database.sdb");
    return true;
  }

  /**
   * {@inheritdoc}
   */
  protected function _getPortableTableColumnDefinition($tableColumn)
  {
    $parts = explode('(', $tableColumn['type']);
    $tableColumn['type'] = trim($parts[0]);
    if (isset($parts[1])) {
      $length = trim($parts[1], ')');
      $tableColumn['length'] = $length;
    }

    $dbType = strtolower($tableColumn['type']);
    $length = isset($tableColumn['length']) ? $tableColumn['length'] : null;
    $unsigned = false;

    if (strpos($dbType, ' unsigned') !== false) {
      $dbType = str_replace(' unsigned', '', $dbType);
      $unsigned = true;
    }

    $fixed = false;
    $type = $this->_platform->getDoctrineTypeMapping($dbType);
    $default = $tableColumn['dflt_value'];
    if ($default == 'NULL') {
      $default = null;
    }
    if ($default !== null) {
      // SQLite returns strings wrapped in single quotes, so we need to strip them
      $default = preg_replace("/^'(.*)'$/", '\1', $default);
    }
    $notnull = (bool) $tableColumn['notnull'];

    if ( ! isset($tableColumn['name'])) {
      $tableColumn['name'] = '';
    }

    $precision = null;
    $scale = null;

    switch ($dbType) {
      case 'char':
        $fixed = true;
        break;
      case 'float':
      case 'double':
      case 'real':
      case 'decimal':
      case 'numeric':
        if (isset($tableColumn['length'])) {
          if (strpos($tableColumn['length'], ',') === false) {
            $tableColumn['length'] .= ",0";
          }
          list($precision, $scale) = array_map('trim', explode(',', $tableColumn['length']));
        }
        $length = null;
        break;
    }

    $options = array(
      'length'   => $length,
      'unsigned' => (bool) $unsigned,
      'fixed'    => $fixed,
      'notnull'  => $notnull,
      'default'  => $default,
      'precision' => $precision,
      'scale'     => $scale,
      'autoincrement' => false,
    );

    return new Column($tableColumn['name'], Type::getType($type), $options);
  }

  /**
   * {@inheritdoc}
   *
   * @license New BSD License
   * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
   */
  protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
  {
    $indexBuffer = array();

    // fetch primary
    $stmt = $this->_conn->executeQuery("PRAGMA TABLE_INFO ('$tableName')");
    $indexArray = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    usort($indexArray, function($a, $b) {
      if ($a['pk'] == $b['pk']) {
        return $a['cid'] - $b['cid'];
      }

      return $a['pk'] - $b['pk'];
    });
    foreach ($indexArray as $indexColumnRow) {
      if ($indexColumnRow['pk'] != "0") {
        $indexBuffer[] = array(
          'key_name' => 'primary',
          'primary' => true,
          'non_unique' => false,
          'column_name' => $indexColumnRow['name']
        );
      }
    }

    // fetch regular indexes
    foreach ($tableIndexes as $tableIndex) {
      // Ignore indexes with reserved names, e.g. autoindexes
      if (strpos($tableIndex['name'], 'sqlite_') !== 0) {
        $keyName = $tableIndex['name'];
        $idx = array();
        $idx['key_name'] = $keyName;
        $idx['primary'] = false;
        $idx['non_unique'] = $tableIndex['unique']?false:true;

        $stmt = $this->_conn->executeQuery("PRAGMA INDEX_INFO ('{$keyName}')");
        $indexArray = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($indexArray as $indexColumnRow) {
          $idx['column_name'] = $indexColumnRow['name'];
          $indexBuffer[] = $idx;
        }
      }
    }

    return $this->getPortableTableIndexesList($indexBuffer, $tableName);
  }
}
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

  public function hasTable($table)
  {
    DB::connection()->setTablePrefix('');
    return DB::schema()->hasTable($table);
  }

  public function listTableNames($database)
  {
    $q = "SELECT name as table_name FROM sqlite_master 
        WHERE type = 'table' 
        AND name != 'sqlite_sequence' 
        AND name != 'geometry_columns' 
        AND name != 'spatial_ref_sys'
         UNION ALL SELECT name FROM sqlite_temp_master 
        WHERE type = 'table' ORDER BY name";
    return DB::select($q);
  }

  public function getEnum($table, $database)
  {
    return [];//sqlite no enum
  }

  public function listTableColumns($table, $database)
  {
    $table = str_replace('.', '__', $table);
    $q = "PRAGMA table_info('$table')";

    $v= DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);

    return $this->_getPortableTableColumnList($table, $database, $v);
  }

  public function listTableIndexes($table, $database)
  {
    $table = str_replace('.', '__', $table);
    $q = "PRAGMA index_list('$table')";

    $i= DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);

    return $this->_getPortableTableIndexesList($i, $table);
  }

  public function listTableForeignKeys($table, $database)
  {
    $table = str_replace('.', '__', $table);

    $q = "PRAGMA foreign_key_list('$table')";

    $tableForeignKeys = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);

    if ( ! empty($tableForeignKeys)) {
      $q = "SELECT sql FROM (SELECT * FROM sqlite_master UNION ALL SELECT * FROM sqlite_temp_master) WHERE type = 'table' AND name = '$table'";
      $createSql = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
      $createSql = isset($createSql[0]['sql']) ? $createSql[0]['sql'] : '';
      if (preg_match_all('#
                    (?:CONSTRAINT\s+([^\s]+)\s+)?
                    (?:FOREIGN\s+KEY[^\)]+\)\s*)?
                    REFERENCES\s+[^\s]+\s+(?:\([^\)]+\))?
                    (?:
                        [^,]*?
                        (NOT\s+DEFERRABLE|DEFERRABLE)
                        (?:\s+INITIALLY\s+(DEFERRED|IMMEDIATE))?
                    )?#isx',
        $createSql, $match)) {

        $names = array_reverse($match[1]);
        $deferrable = array_reverse($match[2]);
        $deferred = array_reverse($match[3]);
      } else {
        $names = $deferrable = $deferred = array();
      }

      foreach ($tableForeignKeys as $key => $value) {
        $id = $value['id'];
        $tableForeignKeys[$key]['constraint_name'] = isset($names[$id]) && '' != $names[$id] ? $names[$id] : $id;
        $tableForeignKeys[$key]['deferrable'] = isset($deferrable[$id]) && 'deferrable' == strtolower($deferrable[$id]) ? true : false;
        $tableForeignKeys[$key]['deferred'] = isset($deferred[$id]) && 'deferred' == strtolower($deferred[$id]) ? true : false;
      }
    }

    $tableForeignKeys = $this->getPortableTableForeignKeysList($tableForeignKeys);
    $list = [];
    foreach ($tableForeignKeys as $value) {
      $list[] = $value;
    }
    return $list;
  }

  public function createDatabase($schemaName, $charset, $collation, $cfg)
  {
    return (new \PDO('sqlite:'.$cfg['database'])) ? true : false;
  }

  //////////////////////////////
  /**
   * Independent of the database the keys of the column list result are lowercased.
   *
   * The name of the created column instance however is kept in its case.
   *
   * @param string $table        The name of the table.
   * @param string $database
   * @param array  $tableColumns
   *
   * @return array
   */
  protected function getPortableTableColumnList($table, $database, $tableColumns)
  {
    $list = [];
    foreach ($tableColumns as $tableColumn) {
      $column = null;
      $defaultPrevented = false;

      if ( ! $defaultPrevented) {
        $column = $this->_getPortableTableColumnDefinition($tableColumn);
      }

      if ($column) {
//        $name = strtolower($column->getQuotedName($this->_platform));
        $list['sqlite'] = $column;
      }
    }

    return $list;
  }
  /**
   * {@inheritdoc}
   */
  protected function _getPortableTableColumnList($table, $database, $tableColumns)
  {
    $list = $this->getPortableTableColumnList($table, $database, $tableColumns);

    // find column with autoincrement
    $autoincrementColumn = null;
    $autoincrementCount = 0;

    foreach ($tableColumns as $tableColumn) {
      if ('0' != $tableColumn['pk']) {
        $autoincrementCount++;
        if (null === $autoincrementColumn && 'integer' === strtolower($tableColumn['type'])) {
          $autoincrementColumn = $tableColumn['name'];
        }
      }
    }

    if (1 == $autoincrementCount && null !== $autoincrementColumn) {
      foreach ($list as $column) {
        if ($autoincrementColumn == $column->getName()) {
          $column->setAutoincrement(true);
        }
      }
    }

    // inspect column collation
    $q = "SELECT sql FROM (SELECT * FROM sqlite_master UNION ALL SELECT * FROM sqlite_temp_master) WHERE type = 'table' AND name = '$table'";
    $createSql = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
    $createSql = isset($createSql[0]['sql']) ? $createSql[0]['sql'] : '';

    foreach ($list as $columnName => $column) {
      $type = $column->getType();
//      if ($type instanceof StringType || $type instanceof TextType) {
      if ($type === 'string' || $type === 'text') {
        $column->setPlatformOption('collation', $this->parseColumnCollationFromSQL($columnName, $createSql) ?: 'BINARY');
      }
    }

    return $list;
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
    $type = $this->mapType($dbType);
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
            $tableColumn['length'] .= ',0';
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

//    return new Column($tableColumn['name'], Type::getType($type), $options);
    return new Column($tableColumn['name'], $type, $options);
  }

  private function parseColumnCollationFromSQL($column, $sql)
  {
    if (preg_match(
      '{(?:'.preg_quote($column).'|'.preg_quote($this->quoteSingleIdentifier($column)).')
                [^,(]+(?:\([^()]+\)[^,]*)?
                (?:(?:DEFAULT|CHECK)\s*(?:\(.*?\))?[^,]*)*
                COLLATE\s+["\']?([^\s,"\')]+)}isx', $sql, $match)) {
      return $match[1];
    }

    return false;
  }

  /**
   * Quotes a single identifier (no dot chain separation).
   *
   * @param string $str The identifier name to be quoted.
   *
   * @return string The quoted identifier string.
   */
  public function quoteSingleIdentifier($str)
  {
    $c = '"';//$this->getIdentifierQuoteCharacter();

    return $c . str_replace($c, $c.$c, $str) . $c;
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
    $indexArray = DB::connection()->getPdo()->query("PRAGMA TABLE_INFO ('$tableName')")->fetchAll(\PDO::FETCH_ASSOC);
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

        $indexArray = DB::connection()->getPdo()->query("PRAGMA INDEX_INFO ('{$keyName}')")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($indexArray as $indexColumnRow) {
          $idx['column_name'] = $indexColumnRow['name'];
          $indexBuffer[] = $idx;
        }
      }
    }

    return $this->getPortableTableIndexesList($indexBuffer, $tableName);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPortableTableForeignKeysList($tableForeignKeys)
  {
    $list = array();
    foreach ($tableForeignKeys as $value) {
      $value = array_change_key_case($value, CASE_LOWER);
      $name = $value['constraint_name'];
      if ( ! isset($list[$name])) {
        if ( ! isset($value['on_delete']) || $value['on_delete'] === 'RESTRICT') {
          $value['on_delete'] = null;
        }
        if ( ! isset($value['on_update']) || $value['on_update'] === 'RESTRICT') {
          $value['on_update'] = null;
        }

        $list[$name] = array(
          'name' => $name,
          'local' => array(),
          'foreign' => array(),
          'foreignTable' => $value['table'],
          'onDelete' => $value['on_delete'],
          'onUpdate' => $value['on_update'],
          'deferrable' => $value['deferrable'],
          'deferred'=> $value['deferred'],
        );
      }
      $list[$name]['local'][] = $value['from'];
      $list[$name]['foreign'][] = $value['to'];
    }

    $result = array();
    foreach ($list as $constraint) {
      $result[] = new ForeignKeyConstraint(
        array_values($constraint['local']), $constraint['foreignTable'],
        array_values($constraint['foreign']), $constraint['name'],
        array(
          'onDelete' => $constraint['onDelete'],
          'onUpdate' => $constraint['onUpdate'],
          'deferrable' => $constraint['deferrable'],
          'deferred'=> $constraint['deferred'],
        )
      );
    }

    return $result;
  }
}
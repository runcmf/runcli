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
use PDO;

class MySql extends Common implements AdapterInterface
{
    const LENGTH_LIMIT_TINYTEXT = 255;
    const LENGTH_LIMIT_TEXT = 65535;
    const LENGTH_LIMIT_MEDIUMTEXT = 16777215;

    const LENGTH_LIMIT_TINYBLOB = 255;
    const LENGTH_LIMIT_BLOB = 65535;
    const LENGTH_LIMIT_MEDIUMBLOB = 16777215;

    protected $doctrineTypeMapping = array(
        'tinyint' => 'boolean',
        'smallint' => 'smallint',
        'mediumint' => 'integer',
        'int' => 'integer',
        'integer' => 'integer',
        'bigint' => 'bigint',
        'tinytext' => 'text',
        'mediumtext' => 'text',
        'longtext' => 'text',
        'text' => 'text',
        'varchar' => 'string',
        'string' => 'string',
        'char' => 'string',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
        'time' => 'time',
        'float' => 'float',
        'double' => 'float',
        'real' => 'float',
        'decimal' => 'decimal',
        'numeric' => 'decimal',
        'year' => 'date',
        'longblob' => 'blob',
        'blob' => 'blob',
        'mediumblob' => 'blob',
        'tinyblob' => 'blob',
        'binary' => 'binary',
        'varbinary' => 'binary',
        'set' => 'simple_array',
    );

    public function hasTable($table)
    {
        DB::connection()->setTablePrefix('');
        return DB::schema()->hasTable($table);
    }

    public function listTableNames($database)
    {
        $q = 'SELECT table_name FROM information_schema.tables WHERE table_schema = "' . $database . '"';
        return DB::select($q);
    }

    public function getEnum($table, $database)
    {
        $q = "SELECT
            column_name, column_type
          FROM
            information_schema.columns
          WHERE
            table_schema = '$database' AND table_name = '$table' AND data_type = 'enum'";
        return DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listTableColumns($table, $database)
    {
        if ($database) {
            $database = "'" . $database . "'";
        } else {
            $database = 'DATABASE()';
        }

        $q = 'SELECT COLUMN_NAME AS Field, COLUMN_TYPE AS Type, IS_NULLABLE AS `Null`, ' .
            'COLUMN_KEY AS `Key`, COLUMN_DEFAULT AS `Default`, EXTRA AS Extra, COLUMN_COMMENT AS Comment, ' .
            'CHARACTER_SET_NAME AS CharacterSet, COLLATION_NAME AS Collation ' .
            'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ' . $database . " AND TABLE_NAME = '" . $table . "'";

        $t = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
        $list = [];
        foreach ($t as $tableColumn) {
            $column = $this->_getPortableTableColumnDefinition($tableColumn);
            if ($column) {
                $list[] = $column;
            }
        }
        return $list;
    }

    public function listTableIndexes($table, $database)
    {
        if ($database) {
            $q = 'SELECT TABLE_NAME AS `Table`, NON_UNIQUE AS Non_Unique, INDEX_NAME AS Key_name, ' .
                'SEQ_IN_INDEX AS Seq_in_index, COLUMN_NAME AS Column_Name, COLLATION AS Collation, ' .
                'CARDINALITY AS Cardinality, SUB_PART AS Sub_Part, PACKED AS Packed, ' .
                'NULLABLE AS `Null`, INDEX_TYPE AS Index_Type, COMMENT AS Comment ' .
                "FROM information_schema.STATISTICS WHERE TABLE_NAME = '" . $table . "' AND TABLE_SCHEMA = '" . $database . "'";
        } else {
            $q = 'SHOW INDEX FROM ' . $table;
        }
        $t = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);

        return $this->_getPortableTableIndexesList($t, $tableName = null);
    }

    public function listTableForeignKeys($table, $database)
    {
        $q = 'SELECT DISTINCT k.`CONSTRAINT_NAME`, k.`COLUMN_NAME`, k.`REFERENCED_TABLE_NAME`, ' .
            'k.`REFERENCED_COLUMN_NAME` /*!50116 , c.update_rule, c.delete_rule */ ' .
            'FROM information_schema.key_column_usage k /*!50116 ' .
            'INNER JOIN information_schema.referential_constraints c ON ' .
            '  c.constraint_name = k.constraint_name AND ' .
            "  c.table_name = '$table' */ WHERE k.table_name = '$table'";
        if ($database) {
            $q .= " AND k.table_schema = '$database' /*!50116 AND c.constraint_schema = '$database' */";
        }
        $q .= ' AND k.`REFERENCED_COLUMN_NAME` is not NULL';

        $t = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);

        return $this->_getPortableTableForeignKeysList($t);
    }

    public function createDatabase($schemaName, $charset, $collation, $cfg)
    {
        $charset = $charset ?: 'utf8';
        $collation = $collation ?: 'utf8_general_ci';
        $q = 'CREATE DATABASE IF NOT EXISTS `' . $schemaName . '` DEFAULT CHARACTER SET `' . $charset . '` COLLATE `' . $collation . '`;';
        $dsn = $cfg['driver'] . ':host=' . $cfg['host'];
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $opt);
        return $pdo->exec($q
//                ."CREATE USER '".$cfg['username']."'@'localhost' IDENTIFIED BY '".$cfg['password']."';
//                GRANT ALL ON `$schemaName`.* TO '".$cfg['username']."'@'localhost';
//                FLUSH PRIVILEGES;"
        ) or die(print_r($pdo->errorInfo(), true));
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['type']);
        $dbType = strtok($dbType, '(), ');
        if (isset($tableColumn['length'])) {
            $length = $tableColumn['length'];
        } else {
            $length = strtok('(), ');
        }

        $fixed = null;

        if (!isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $scale = null;
        $precision = null;

        $type = $this->mapType($dbType);

        // In cases where not connected to a database DESCRIBE $table does not return 'Comment'
        if (isset($tableColumn['comment'])) {
            $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
            $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);
        }

        switch ($dbType) {
            case 'char':
            case 'binary':
                $fixed = true;
                break;
            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                if (preg_match('([A-Za-z]+\(([0-9]+)\,([0-9]+)\))', $tableColumn['type'], $match)) {
                    $precision = $match[1];
                    $scale = $match[2];
                    $length = null;
                }
                break;
            case 'tinytext':
                $length = self::LENGTH_LIMIT_TINYTEXT;
                break;
            case 'text':
                $length = self::LENGTH_LIMIT_TEXT;
                break;
            case 'mediumtext':
                $length = self::LENGTH_LIMIT_MEDIUMTEXT;
                break;
            case 'tinyblob':
                $length = self::LENGTH_LIMIT_TINYBLOB;
                break;
            case 'blob':
                $length = self::LENGTH_LIMIT_BLOB;
                break;
            case 'mediumblob':
                $length = self::LENGTH_LIMIT_MEDIUMBLOB;
                break;
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'bigint':
            case 'year':
                $length = null;
                break;
        }

        $length = ((int)$length == 0) ? null : (int)$length;
        $options = array(
            'length' => $length,
            'unsigned' => (bool)(strpos($tableColumn['type'], 'unsigned') !== false),
            'fixed' => (bool)$fixed,
            'default' => isset($tableColumn['default']) ? $tableColumn['default'] : null,
            'notnull' => (bool)($tableColumn['null'] !== 'YES'),
            'scale' => null,
            'precision' => null,
            'autoincrement' => (bool)(strpos($tableColumn['extra'], 'auto_increment') !== false),
            'comment' => isset($tableColumn['comment']) && $tableColumn['comment'] !== ''
                ? $tableColumn['comment']
                : null,
        );

        if ($scale !== null && $precision !== null) {
            $options['scale'] = $scale;
            $options['precision'] = $precision;
        }

//    $column = new Column($tableColumn['field'], Type::getType($type), $options);
        $column = new Column($tableColumn['field'], $type, $options);

        if (isset($tableColumn['collation'])) {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        return $column;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        foreach ($tableIndexes as $k => $v) {
            $v = array_change_key_case($v, CASE_LOWER);
            if ($v['key_name'] == 'PRIMARY') {
                $v['primary'] = true;
            } else {
                $v['primary'] = false;
            }
            if (strpos($v['index_type'], 'FULLTEXT') !== false) {
                $v['flags'] = array('FULLTEXT');
            } elseif (strpos($v['index_type'], 'SPATIAL') !== false) {
                $v['flags'] = array('SPATIAL');
            }
            $tableIndexes[$k] = $v;
        }

        return $this->getPortableTableIndexesList($tableIndexes, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $list = [];
        foreach ($tableForeignKeys as $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            if (!isset($list[$value['constraint_name']])) {
                if (!isset($value['delete_rule']) || $value['delete_rule'] == 'RESTRICT') {
                    $value['delete_rule'] = null;
                }
                if (!isset($value['update_rule']) || $value['update_rule'] == 'RESTRICT') {
                    $value['update_rule'] = null;
                }

                $list[$value['constraint_name']] = [
                    'name' => $value['constraint_name'],
                    'local' => [],
                    'foreign' => [],
//          'foreignTable' => $value['referenced_table_name'],
                    'foreignTable' => str_replace(DB::connection()->getConfig('prefix'), '', $value['referenced_table_name']),
                    'onDelete' => $value['delete_rule'],
                    'onUpdate' => $value['update_rule'],
                ];
            }
            $list[$value['constraint_name']]['local'][] = $value['column_name'];
            $list[$value['constraint_name']]['foreign'][] = $value['referenced_column_name'];
        }

        $result = [];
        foreach ($list as $constraint) {
            $result[] = new ForeignKeyConstraint(
                array_values($constraint['local']), $constraint['foreignTable'],
                array_values($constraint['foreign']), $constraint['name'],
                [
                    'onDelete' => $constraint['onDelete'],
                    'onUpdate' => $constraint['onUpdate'],
                ]
            );
        }

        return $result;
    }
}
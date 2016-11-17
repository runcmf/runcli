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

class PgSql extends Common implements AdapterInterface
{
    protected $doctrineTypeMapping = [
        'smallint' => 'smallint',
        'int2' => 'smallint',
        'serial' => 'integer',
        'serial4' => 'integer',
        'int' => 'integer',
        'int4' => 'integer',
        'integer' => 'integer',
        'bigserial' => 'bigint',
        'serial8' => 'bigint',
        'bigint' => 'bigint',
        'int8' => 'bigint',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'text' => 'text',
        'varchar' => 'string',
        'interval' => 'string',
        '_varchar' => 'string',
        'char' => 'string',
        'bpchar' => 'string',
        'inet' => 'string',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
        'timestamptz' => 'datetimetz',
        'time' => 'time',
        'timetz' => 'time',
        'float' => 'float',
        'float4' => 'float',
        'float8' => 'float',
        'double' => 'float',
        'double precision' => 'float',
        'real' => 'float',
        'decimal' => 'decimal',
        'money' => 'decimal',
        'numeric' => 'decimal',
        'year' => 'date',
        'uuid' => 'guid',
        'bytea' => 'blob',
    ];

    public function hasTable($table)
    {
//    $q = "SELECT count(*) FROM information_schema.tables WHERE table_name = '$table';";
//    $t = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
//    return (isset($t[0]) && $t[0] > 0);
        DB::connection()->setTablePrefix('');// check exist config 'schema'   => 'your_schema'
        return DB::schema()->hasTable($table);
    }

    public function listTableNames($database)
    {
        $q = "SELECT quote_ident(table_name) AS table_name,
                       table_schema AS schema_name
                FROM   information_schema.tables
                WHERE  table_schema NOT LIKE 'pg_%'
                AND    table_schema != 'information_schema'
                AND    table_name != 'geometry_columns'
                AND    table_name != 'spatial_ref_sys'
                AND    table_type != 'VIEW'";

        return DB::select(DB::raw($q));
    }

    public function getEnum($table, $database)
    {
        $q = "SELECT c.column_name, 
            (SELECT array_agg(e.enumlabel)
               FROM pg_catalog.pg_type t
               JOIN pg_catalog.pg_enum e ON t.oid=e.enumtypid
               WHERE t.typname=c.udt_name) AS column_type
          FROM information_schema.columns c
          WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
            AND table_name = '$table'
            AND data_type = 'USER-DEFINED'";
        return DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listTableColumns($table, $database)
    {
        $q = "SELECT a.attnum,
            quote_ident(a.attname) AS field,
            t.typname AS type,
            format_type(a.atttypid, a.atttypmod) AS complete_type,
            (SELECT t1.typname FROM pg_catalog.pg_type t1 WHERE t1.oid = t.typbasetype) AS domain_type,
            (SELECT format_type(t2.typbasetype, t2.typtypmod) FROM
              pg_catalog.pg_type t2 WHERE t2.typtype = 'd' AND t2.oid = a.atttypid) AS domain_complete_type,
            a.attnotnull AS isnotnull,
            (SELECT 't'
             FROM pg_index
             WHERE c.oid = pg_index.indrelid
                AND pg_index.indkey[0] = a.attnum
                AND pg_index.indisprimary = 't'
            ) AS pri,
            (SELECT pg_get_expr(adbin, adrelid)
             FROM pg_attrdef
             WHERE c.oid = pg_attrdef.adrelid
                AND pg_attrdef.adnum=a.attnum
            ) AS default,
            (SELECT pg_description.description
                FROM pg_description WHERE pg_description.objoid = c.oid AND a.attnum = pg_description.objsubid
            ) AS comment
            FROM pg_attribute a, pg_class c, pg_type t, pg_namespace n
            WHERE " . $this->getTableWhereClause($table, 'c', 'n') . "
                AND a.attnum > 0
                AND a.attrelid = c.oid
                AND a.atttypid = t.oid
                AND n.oid = c.relnamespace
            ORDER BY a.attnum";
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
        $q = "SELECT quote_ident(relname) as relname, pg_index.indisunique, pg_index.indisprimary,
                 pg_index.indkey, pg_index.indrelid,
                 pg_get_expr(indpred, indrelid) AS where
           FROM pg_class, pg_index
           WHERE oid IN (
              SELECT indexrelid
              FROM pg_index si, pg_class sc, pg_namespace sn
              WHERE " . $this->getTableWhereClause($table, 'sc', 'sn') . " AND sc.oid=si.indrelid AND sc.relnamespace = sn.oid
           ) AND pg_index.indexrelid = oid";

        $t = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);

        return $this->_getPortableTableIndexesList($t, $tableName = null);
    }

    public function listTableForeignKeys($table, $database)
    {
        $q = "SELECT 
            quote_ident(r.conname) as conname,
            pg_catalog.pg_get_constraintdef(r.oid, true) as condef
          FROM pg_catalog.pg_constraint r
          WHERE r.conrelid =
          (
              SELECT c.oid
              FROM pg_catalog.pg_class c, pg_catalog.pg_namespace n
              WHERE " . $this->getTableWhereClause($table) . " 
              AND n.oid = c.relnamespace
          )
          AND r.contype = 'f'";

        $t = DB::connection()->getPdo()->query($q)->fetchAll(\PDO::FETCH_ASSOC);
        $list = [];
        foreach ($t as $value) {
            if ($value = $this->_getPortableTableForeignKeyDefinition($value)) {
                $list[] = $value;
            }
        }

        return $list;
    }

    public function createDatabase($schemaName, $charset, $collation, $cfg)
    {
//    if($charset === 'default'){
//      $q = "CREATE DATABASE $schemaName;";
//    } else {
        $charset = $charset ?: 'UTF8';
        $collation = $collation ?: 'en_US.UTF-8';
        $q = "CREATE DATABASE $schemaName ENCODING = '$charset' LC_CTYPE = '$collation' LC_COLLATE = '$collation';";
//    }

        $dsn = $cfg['driver'] . ':host=' . $cfg['host'] . ';dbname=\'template1\'';//default db

        try {
            $dbh = new PDO($dsn, $cfg['username'], $cfg['password']);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($dbh->exec($q) === 0) {
                return true;
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            echo "\n\n" . $e->getMessage();
        }
        return false;
    }

    ///////
    /**
     * @param string $table
     * @param string $classAlias
     * @param string $namespaceAlias
     *
     * @return string
     */
    private function getTableWhereClause($table, $classAlias = 'c', $namespaceAlias = 'n')
    {
        $whereClause = $namespaceAlias . ".nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND ";
        if (strpos($table, '.') !== false) {
            list($schema, $table) = explode('.', $table);
            $schema = "'" . $schema . "'";
        } else {
            $schema = "ANY(string_to_array((select replace(replace(setting,'\"\$user\"',user),' ','') from pg_catalog.pg_settings where name = 'search_path'),','))";
        }

        $table = new Identifier($table);
        $whereClause .= "$classAlias.relname = '" . $table->getName() . "' AND $namespaceAlias.nspname = $schema";

        return $whereClause;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        if (strtolower($tableColumn['type']) === 'varchar' || strtolower($tableColumn['type']) === 'bpchar') {
            // get length from varchar definition
            $length = preg_replace('~.*\(([0-9]*)\).*~', '$1', $tableColumn['complete_type']);
            $tableColumn['length'] = $length;
        }

        $matches = array();

        $autoincrement = false;
        if (preg_match("/^nextval\('(.*)'(::.*)?\)$/", $tableColumn['default'], $matches)) {
            $tableColumn['sequence'] = $matches[1];
            $tableColumn['default'] = null;
            $autoincrement = true;
        }

        if (preg_match("/^'(.*)'::.*$/", $tableColumn['default'], $matches)) {
            $tableColumn['default'] = $matches[1];
        }

        if (stripos($tableColumn['default'], 'NULL') === 0) {
            $tableColumn['default'] = null;
        }

        $length = (isset($tableColumn['length'])) ? $tableColumn['length'] : null;
        if ($length == '-1' && isset($tableColumn['atttypmod'])) {
            $length = $tableColumn['atttypmod'] - 4;
        }
        if ((int)$length <= 0) {
            $length = null;
        }
        $fixed = null;

        if (!isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $precision = null;
        $scale = null;

        $dbType = strtolower($tableColumn['type']);
        if (strlen($tableColumn['domain_type']) && !$this->mapType($tableColumn['type'])) {
            $dbType = strtolower($tableColumn['domain_type']);
            $tableColumn['complete_type'] = $tableColumn['domain_complete_type'];
        }

        $type = $this->mapType($dbType);
        $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
        $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);

        switch ($dbType) {
            case 'smallint':
            case 'int2':
                $length = null;
                break;
            case 'int':
            case 'int4':
            case 'integer':
                $length = null;
                break;
            case 'bigint':
            case 'int8':
                $length = null;
                break;
            case 'bool':
            case 'boolean':
                if ($tableColumn['default'] === 'true') {
                    $tableColumn['default'] = true;
                }

                if ($tableColumn['default'] === 'false') {
                    $tableColumn['default'] = false;
                }

                $length = null;
                break;
            case 'text':
                $fixed = false;
                break;
            case 'varchar':
            case 'interval':
            case '_varchar':
                $fixed = false;
                break;
            case 'char':
            case 'bpchar':
                $fixed = true;
                break;
            case 'float':
            case 'float4':
            case 'float8':
            case 'double':
            case 'double precision':
            case 'real':
            case 'decimal':
            case 'money':
            case 'numeric':
                if (preg_match('([A-Za-z]+\(([0-9]+)\,([0-9]+)\))', $tableColumn['complete_type'], $match)) {
                    $precision = $match[1];
                    $scale = $match[2];
                    $length = null;
                }
                break;
            case 'year':
                $length = null;
                break;
        }

        if ($tableColumn['default'] && preg_match("('([^']+)'::)", $tableColumn['default'], $match)) {
            $tableColumn['default'] = $match[1];
        }

        $options = array(
            'length' => $length,
            'notnull' => (bool)$tableColumn['isnotnull'],
            'default' => $tableColumn['default'],
            'primary' => (bool)($tableColumn['pri'] == 't'),
            'precision' => $precision,
            'scale' => $scale,
            'fixed' => $fixed,
            'unsigned' => false,
            'autoincrement' => $autoincrement,
            'comment' => isset($tableColumn['comment']) && $tableColumn['comment'] !== ''
                ? $tableColumn['comment']
                : null,
        );
//die($type);
//    $column = new Column($tableColumn['field'], Type::getType($type), $options);
        $column = new Column($tableColumn['field'], $type, $options);

        if (isset($tableColumn['collation']) && !empty($tableColumn['collation'])) {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        return $column;
    }

    /**
     * {@inheritdoc}
     *
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $buffer = array();
        foreach ($tableIndexes as $row) {
            $colNumbers = explode(' ', $row['indkey']);
            $colNumbersSql = 'IN (' . join(' ,', $colNumbers) . ' )';
            $columnNameSql = "SELECT attnum, attname FROM pg_attribute
                WHERE attrelid={$row['indrelid']} AND attnum $colNumbersSql ORDER BY attnum ASC;";

            $indexColumns = DB::connection()->getPdo()->query($columnNameSql)->fetchAll();

            // required for getting the order of the columns right.
            foreach ($colNumbers as $colNum) {
                foreach ($indexColumns as $colRow) {
                    if ($colNum == $colRow['attnum']) {
                        $buffer[] = array(
                            'key_name' => $row['relname'],
                            'column_name' => trim($colRow['attname']),
                            'non_unique' => !$row['indisunique'],
                            'primary' => $row['indisprimary'],
                            'where' => $row['where'],
                        );
                    }
                }
            }
        }

        return $this->getPortableTableIndexesList($buffer, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        $onUpdate = null;
        $onDelete = null;

        if (empty($tableForeignKey)) {
            return [];
        }
        if (preg_match('(ON UPDATE ([a-zA-Z0-9]+( (NULL|ACTION|DEFAULT))?))', $tableForeignKey['condef'], $match)) {
            $onUpdate = $match[1];
        }
        if (preg_match('(ON DELETE ([a-zA-Z0-9]+( (NULL|ACTION|DEFAULT))?))', $tableForeignKey['condef'], $match)) {
            $onDelete = $match[1];
        }

        if (preg_match('/FOREIGN KEY \((.+)\) REFERENCES (.+)\((.+)\)/', $tableForeignKey['condef'], $values)) {
            // PostgreSQL returns identifiers that are keywords with quotes, we need them later, don't get
            // the idea to trim them here.
            $localColumns = array_map('trim', explode(',', $values[1]));
            $foreignColumns = array_map('trim', explode(',', $values[3]));
            $foreignTable = $values[2];
        }

        return new ForeignKeyConstraint(
            $localColumns, $foreignTable, $foreignColumns, $tableForeignKey['conname'],
            array('onUpdate' => $onUpdate, 'onDelete' => $onDelete)
        );
    }
}
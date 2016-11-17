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

class SqlSrv extends Common implements AdapterInterface
{
    protected $doctrineTypeMapping = array(
        'bigint' => 'bigint',
        'numeric' => 'decimal',
        'bit' => 'boolean',
        'smallint' => 'smallint',
        'decimal' => 'decimal',
        'smallmoney' => 'integer',
        'int' => 'integer',
        'tinyint' => 'smallint',
        'money' => 'integer',
        'float' => 'float',
        'real' => 'float',
        'double' => 'float',
        'double precision' => 'float',
        'smalldatetime' => 'datetime',
        'datetime' => 'datetime',
        'char' => 'string',
        'varchar' => 'string',
        'text' => 'text',
        'nchar' => 'string',
        'nvarchar' => 'string',
        'ntext' => 'text',
        'binary' => 'binary',
        'varbinary' => 'binary',
        'image' => 'blob',
        'uniqueidentifier' => 'guid',
    );

    public function hasTable($table)
    {
        return DB::schema()->hasTable($table);
    }

    public function listTableNames($database)
    {
        // "sysdiagrams" table must be ignored as it's internal SQL Server table for Database Diagrams
        // Category 2 must be ignored as it is "MS SQL Server 'pseudo-system' object[s]" for replication
        return "SELECT name FROM sysobjects WHERE type = 'U' AND name != 'sysdiagrams' AND category != 2 ORDER BY name";
    }

    public function getEnum($table, $database)
    {

    }

    public function listTableColumns($table, $database)
    {
        return "SELECT    col.name,
                          type.name AS type,
                          col.max_length AS length,
                          ~col.is_nullable AS notnull,
                          def.definition AS [default],
                          col.scale,
                          col.precision,
                          col.is_identity AS autoincrement,
                          col.collation_name AS collation,
                          CAST(prop.value AS NVARCHAR(MAX)) AS comment -- CAST avoids driver error for sql_variant type
                FROM      sys.columns AS col
                JOIN      sys.types AS type
                ON        col.user_type_id = type.user_type_id
                JOIN      sys.objects AS obj
                ON        col.object_id = obj.object_id
                JOIN      sys.schemas AS scm
                ON        obj.schema_id = scm.schema_id
                LEFT JOIN sys.default_constraints def
                ON        col.default_object_id = def.object_id
                AND       col.object_id = def.parent_object_id
                LEFT JOIN sys.extended_properties AS prop
                ON        obj.object_id = prop.major_id
                AND       col.column_id = prop.minor_id
                AND       prop.name = 'MS_Description'
                WHERE     obj.type = 'U'
                AND       " . $this->getTableWhereClause($table, 'scm.name', 'obj.name');
    }

    public function listTableIndexes($table, $database)
    {
        $q = "SELECT idx.name AS key_name,
                       col.name AS column_name,
                       ~idx.is_unique AS non_unique,
                       idx.is_primary_key AS [primary],
                       CASE idx.type
                           WHEN '1' THEN 'clustered'
                           WHEN '2' THEN 'nonclustered'
                           ELSE NULL
                       END AS flags
                FROM sys.tables AS tbl
                JOIN sys.schemas AS scm ON tbl.schema_id = scm.schema_id
                JOIN sys.indexes AS idx ON tbl.object_id = idx.object_id
                JOIN sys.index_columns AS idxcol ON idx.object_id = idxcol.object_id AND idx.index_id = idxcol.index_id
                JOIN sys.columns AS col ON idxcol.object_id = col.object_id AND idxcol.column_id = col.column_id
                WHERE " . $this->getTableWhereClause($table, 'scm.name', 'tbl.name') . "
                ORDER BY idx.index_id ASC, idxcol.key_ordinal ASC";
    }

    public function listTableForeignKeys($table, $database)
    {
        $q = "SELECT f.name AS ForeignKey,
                SCHEMA_NAME (f.SCHEMA_ID) AS SchemaName,
                OBJECT_NAME (f.parent_object_id) AS TableName,
                COL_NAME (fc.parent_object_id,fc.parent_column_id) AS ColumnName,
                SCHEMA_NAME (o.SCHEMA_ID) ReferenceSchemaName,
                OBJECT_NAME (f.referenced_object_id) AS ReferenceTableName,
                COL_NAME(fc.referenced_object_id,fc.referenced_column_id) AS ReferenceColumnName,
                f.delete_referential_action_desc,
                f.update_referential_action_desc
                FROM sys.foreign_keys AS f
                INNER JOIN sys.foreign_key_columns AS fc
                INNER JOIN sys.objects AS o ON o.OBJECT_ID = fc.referenced_object_id
                ON f.OBJECT_ID = fc.constraint_object_id
                WHERE " . $this->getTableWhereClause($table, 'SCHEMA_NAME (f.schema_id)', 'OBJECT_NAME (f.parent_object_id)');
    }

    public function createDatabase($schemaName, $charset, $collation, $cfg)
    {
        $q = 'CREATE DATABASE ' . $schemaName;
    }

    //////////////
    /**
     * Returns the where clause to filter schema and table name in a query.
     *
     * @param string $table The full qualified name of the table.
     * @param string $schemaColumn The name of the column to compare the schema to in the where clause.
     * @param string $tableColumn The name of the column to compare the table to in the where clause.
     *
     * @return string
     */
    private function getTableWhereClause($table, $schemaColumn, $tableColumn)
    {
        if (strpos($table, '.') !== false) {
            list($schema, $table) = explode('.', $table);
            $schema = "'" . $schema . "'";
        } else {
            $schema = 'SCHEMA_NAME()';
        }

        return "({$tableColumn} = '{$table}' AND {$schemaColumn} = {$schema})";
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtok($tableColumn['type'], '(), ');
        $fixed = null;
        $length = (int)$tableColumn['length'];
        $default = $tableColumn['default'];

        if (!isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        while ($default != ($default2 = preg_replace("/^\((.*)\)$/", '$1', $default))) {
            $default = trim($default2, "'");

            if ($default == 'getdate()') {
                $default = $this->_platform->getCurrentTimestampSQL();
            }
        }

        switch ($dbType) {
            case 'nchar':
            case 'nvarchar':
            case 'ntext':
                // Unicode data requires 2 bytes per character
                $length = $length / 2;
                break;
            case 'varchar':
                // TEXT type is returned as VARCHAR(MAX) with a length of -1
                if ($length == -1) {
                    $dbType = 'text';
                }
                break;
        }

        if ('char' === $dbType || 'nchar' === $dbType || 'binary' === $dbType) {
            $fixed = true;
        }

        $type = $this->mapType($dbType);
        $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
        $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);

        $options = array(
            'length' => ($length == 0 || !in_array($type, array('text', 'string'))) ? null : $length,
            'unsigned' => false,
            'fixed' => (bool)$fixed,
            'default' => $default !== 'NULL' ? $default : null,
            'notnull' => (bool)$tableColumn['notnull'],
            'scale' => $tableColumn['scale'],
            'precision' => $tableColumn['precision'],
            'autoincrement' => (bool)$tableColumn['autoincrement'],
            'comment' => $tableColumn['comment'] !== '' ? $tableColumn['comment'] : null,
        );

//    $column = new Column($tableColumn['name'], Type::getType($type), $options);
        $column = new Column($tableColumn['name'], $type, $options);

        if (isset($tableColumn['collation']) && $tableColumn['collation'] !== 'NULL') {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        return $column;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexRows, $tableName = null)
    {
        foreach ($tableIndexRows as &$tableIndex) {
            $tableIndex['non_unique'] = (boolean)$tableIndex['non_unique'];
            $tableIndex['primary'] = (boolean)$tableIndex['primary'];
            $tableIndex['flags'] = $tableIndex['flags'] ? array($tableIndex['flags']) : null;
        }

        return $this->getPortableTableIndexesList($tableIndexRows, $tableName);
    }
}
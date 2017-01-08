<?php namespace RunCli\Generators;

use Illuminate\Database\Capsule\Manager as DB;

class FieldGenerator
{

    /**
     * Convert dbal types to Laravel Migration Types
     * @var array
     */
    protected $fieldTypeMap = [
        'tinyint' => 'tinyInteger',
        'smallint' => 'smallInteger',
        'bigint' => 'bigInteger',
        'datetime' => 'dateTime',
        'blob' => 'binary',
        'int' => 'integer',
        'longtext' => 'longText',
        'varchar' => 'string',
        'varbinary' => 'binary'
    ];

    /**
     * @var string
     */
    protected $database;

    private $tableWithOutPrefix;

    /**
     * Create array of all the fields for a table
     *
     * @param string $table Table Name
     * @param string $database
     * @param bool $ignoreIndexNames
     *
     * @return array|bool
     */
    public function generate($table, $schema, $database, $ignoreIndexNames)
    {
        $this->database = $database;
        $this->tableWithOutPrefix = str_replace($schema->getTablePrefix(), '', $table);
        $columns = $schema->listTableColumns($table);
        if (empty($columns)) {
            return false;
        }
        $indexGenerator = new IndexGenerator();
        $indexGenerator->get($table, $schema, $ignoreIndexNames);
        $fields = $this->setEnum($schema, $this->getFields($columns, $indexGenerator), $table);
        $indexes = $this->getMultiFieldIndexes($indexGenerator);

        return array_merge($fields, $indexes);
    }

    /**
     * @param RunCli\Generators\SchemaGenerator
     * @param array $fields
     * @param string $table
     * @return array
     */
    protected function setEnum(& $schema, array $fields, $table)
    {
        $driver = DB::connection()->getDriverName();
        foreach ($schema->getEnum($table) as $column) {
            $fields[$column['column_name']]['type'] = 'enum';

            //psql (PostgreSQL) 9.4.9 array_agg aggregate to braces {} without quotes
            // json_agg to [] but with double quote
            if ($driver === 'pgsql') {
                $ar = str_replace(['{', '}'], '', $column['column_type']);
//        $ar = str_replace('"','\'',$column['column_type']);
                $fields[$column['column_name']]['args'] = '[\'' . $ar . '\']';
            } else {
                $fields[$column['column_name']]['args'] = str_replace('enum(', 'array(', $column['column_type']);
            }
        }

        return $fields;
    }

    /**
     * @param Doctrine\DBAL\Schema\Column object $columns
     * @param IndexGenerator $indexGenerator
     * @return array
     */
    protected function getFields($columns, IndexGenerator $indexGenerator)
    {
        $fields = [];
        foreach ($columns as $column) {
            $name = $column->getName();
//			$type = $column->getType()->getName();
            $type = $column->getType();
            $length = $column->getLength();
            $default = $column->getDefault();
            $nullable = (!$column->getNotNull());
            $index = $indexGenerator->getIndex($name);

            $decorators = null;
            $args = null;

            if (isset($this->fieldTypeMap[$type])) {
                $type = $this->fieldTypeMap[$type];
            }

            // Different rules for different type groups
            if (in_array($type, ['tinyInteger', 'smallInteger', 'integer', 'bigInteger'])) {
                // Integer
                if ((in_array($type, ['smallInteger', 'integer'])) &&
//          $column->getUnsigned() &&
                    $column->getAutoincrement()
                ) {
                    $type = 'increments';
                    $index = null;
                    $nullable = false;
//				} elseif (
//				  $type == 'tinyInteger' &&
//          $this->compareStr($column->COLUMN_TYPE, 'tinyint(1)') &&
//          !$column->getUnsigned()
//        ) {
//          $type = 'boolean';
//          $index = null;//TODO index boolean ??? we'll let it go at that
                } else {
                    if ($column->getUnsigned()) {
                        $decorators[] = 'unsigned';
                    }
                    if ($column->getAutoincrement()) {
                        $args = 'true';
                        $index = null;
                    }
                }
            } elseif ($type == 'dateTime') {
                if ($name == 'deleted_at' && $nullable) {
                    $nullable = false;
                    $type = 'softDeletes';
                    $name = '';
                } elseif ($name == 'created_at' && isset($fields['updated_at'])) {
                    $fields['updated_at'] = ['field' => '', 'type' => 'timestamps'];
                    continue;
                } elseif ($name == 'updated_at' && isset($fields['created_at'])) {
                    $fields['created_at'] = ['field' => '', 'type' => 'timestamps'];
                    continue;
                }
            } elseif (in_array($type, ['decimal', 'float', 'double'])) {
                // Precision based numbers
                $args = $this->getPrecision($column->getPrecision(), $column->getScale());
                if ($column->getUnsigned()) {
                    $decorators[] = 'unsigned';
                }
            } else {
                // Probably not a number (string/char)
                if ($type === 'string' && $column->getFixed()) {
                    $type = 'char';
                }
                $args = $this->getLength($length);
            }
            if ($nullable) {
                $decorators[] = 'nullable';
            }
            if ($default !== null) {
                $decorators[] = $this->getDefault($default, $type);
            }
            if ($index) {
                $decorators[] = $this->decorate($index->type, $index->name);
            }
            // fix index name for postgres
//      if ($index) $decorators[] = $this->decorate($index->type, $this->tableWithOutPrefix.'_'.$index->name);

            $field = ['field' => $name, 'type' => $type];
            if ($decorators) {
                $field['decorators'] = $decorators;
            }
            if ($args) {
                $field['args'] = $args;
            }
            $fields[$name] = $field;
        }
        return $fields;
    }

    /**
     * @param int $length
     * @return int|void
     */
    protected function getLength($length)
    {
        if ($length && $length !== 255) {
            return $length;
        }
    }

    /**
     * @param string $default
     * @param string $type
     * @return string
     */
    protected function getDefault($default, &$type)
    {
        if (in_array($default, ['CURRENT_TIMESTAMP'])) {
            if ($type == 'dateTime') {
                $type = 'timestamp';
            }
            $default = $this->decorate('DB::raw', $default);
        } elseif (in_array($type, ['string', 'text']) || !is_numeric($default)) {
            $default = $this->argsToString($default);
        }
        return $this->decorate('default', $default, '');
    }

    /**
     * @param int $precision
     * @param int $scale
     * @return string|void
     */
    protected function getPrecision($precision, $scale)
    {
        if ($precision != 8 or $scale != 2) {
            $result = $precision;
            if ($scale != 2) {
                $result .= ', ' . $scale;
            }
            return $result;
        }
    }

    /**
     * @param string|array $args
     * @param string $quotes
     * @return string
     */
    protected function argsToString($args, $quotes = '\'')
    {
        if (is_array($args)) {
            $seperator = $quotes . ', ' . $quotes;
            $args = implode($seperator, $args);
        }

        return $quotes . $args . $quotes;
    }

    /**
     * Get Decorator
     * @param string $function
     * @param string|array $args
     * @param string $quotes
     * @return string
     */
    protected function decorate($function, $args, $quotes = '\'')
    {
        if (!is_null($args)) {
            $args = $this->argsToString($args, $quotes);
            return $function . '(' . $args . ')';
        } else {
            return $function;
        }
    }

    /**
     * @param IndexGenerator $indexGenerator
     * @return array
     */
    protected function getMultiFieldIndexes(IndexGenerator $indexGenerator)
    {
        $indexes = [];
        foreach ($indexGenerator->getMultiFieldIndexes() as $index) {
            $indexArray = [
                'field' => $index->columns,
                'type' => $index->type,
            ];
            if ($index->name) {
                $indexArray['args'] = $this->argsToString($index->name);
                // fix index name for postgres
//        $indexArray['args'] = "'". $this->tableWithOutPrefix . '_' . $index->name ."'";
            }
            $indexes[] = $indexArray;
        }
        return $indexes;
    }
}

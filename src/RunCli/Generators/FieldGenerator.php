<?php namespace RunCli\Generators;

class FieldGenerator {

	/**
	 * Convert dbal types to Laravel Migration Types
	 * @var array
	 */
	protected $fieldTypeMap = [
		'tinyint'  => 'tinyInteger',
		'smallint' => 'smallInteger',
		'bigint'   => 'bigInteger',
		'datetime' => 'dateTime',
		'blob'     => 'binary',
    'int'      => 'integer',
    'longtext' => 'longText',
    'varchar'  => 'string',
    'varbinary'=> 'binary'
	];

	/**
	 * @var string
	 */
	protected $database;

	/**
	 * Create array of all the fields for a table
	 *
	 * @param string                                      $table Table Name
	 * @param string                                      $database
	 * @param bool                                        $ignoreIndexNames
	 *
	 * @return array|bool
	 */
	public function generate($table, $schema, $database, $ignoreIndexNames)
	{
		$this->database = $database;
		$columns = $schema->listTableColumns( $table );
		if ( empty( $columns ) ) return false;
		$indexGenerator = new IndexGenerator($table, $schema, $ignoreIndexNames);
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
		foreach ($schema->getEnum($table) as $column) {
			$fields[$column->column_name]['type'] = 'enum';
			$fields[$column->column_name]['args'] = str_replace('enum(', 'array(', $column->column_type);
		}
		return $fields;
	}

	protected function getColLength($col)//FIXME expand types
  {
    if(in_array($col->DATA_TYPE,
      [
        'int',
        'float',
        'decimal',
        'double'
      ]
    )){
      return $col->NUMERIC_PRECISION;
    }elseif(in_array($col->DATA_TYPE,
      [
        'blob',
        'varchar',
        'varbinary',
        'text',
        'char'
      ]
    )){
      return $col->CHARACTER_MAXIMUM_LENGTH;
    }
  }

  protected function compareStr($str, $needle)
  {
    if(strpos($str, $needle)){
      return true;
    }else{
      return false;
    }
  }

	/**
	 * @param \ArrayObject $columns
	 * @param IndexGenerator $indexGenerator
	 * @return array
	 */
	protected function getFields($columns, IndexGenerator $indexGenerator)
	{
		$fields = [];
		foreach ($columns as $column) {
			$name = $column->COLUMN_NAME;//getName(); //DBAL column method
			$type = $column->DATA_TYPE;//getType()->getName(); expand fieldTypeMap
			$length = $this->getColLength($column);//$column->getLength();
			$default = $column->COLUMN_DEFAULT;//getDefault();
			$nullable = ($column->IS_NULLABLE === 'NO');//getNotNull());
			$index = $indexGenerator->getIndex($name);

			$decorators = null;
			$args = null;

			if (isset($this->fieldTypeMap[$type])) {
				$type = $this->fieldTypeMap[$type];
			}

			// Different rules for different type groups
			if (in_array($type, ['tinyInteger', 'smallInteger', 'integer', 'bigInteger'])) {
				// Integer
        //if ($type == 'integer' and $column->getUnsigned() and $column->getAutoincrement()) {
				if ($type == 'integer' and
          $this->compareStr($column->COLUMN_TYPE, 'unsign') and
          $this->compareStr($column->EXTRA, 'auto_inc')
        ) {
					$type = 'increments';
					$index = null;
				} else {
          //if ($column->getUnsigned()) {
					if ($this->compareStr($column->COLUMN_TYPE, 'unsign')) {
						$decorators[] = 'unsigned';
					}
//          if ($column->getAutoincrement()) {
					if ($this->compareStr($column->EXTRA, 'auto_inc')) {
						$args = 'true';
						$index = null;
					}
				}
			} elseif ($type == 'dateTime') {
				if ($name == 'deleted_at' and $nullable) {
					$nullable = false;
					$type = 'softDeletes';
					$name = '';
				} elseif ($name == 'created_at' and isset($fields['updated_at'])) {
					$fields['updated_at'] = ['field' => '', 'type' => 'timestamps'];
					continue;
				} elseif ($name == 'updated_at' and isset($fields['created_at'])) {
					$fields['created_at'] = ['field' => '', 'type' => 'timestamps'];
					continue;
				}
			} elseif (in_array($type, ['decimal', 'float', 'double'])) {
				// Precision based numbers
				$args = $this->getPrecision(
				  $this->getColLength($column),//$column->getPrecision()
          ($column->NUMERIC_SCALE === null) ? 0 : $column->NUMERIC_SCALE//$column->getScale()
        );
        //if ($column->getUnsigned()) {
				if ($this->compareStr($column->COLUMN_TYPE, 'unsign')) {
					$decorators[] = 'unsigned';
				}
			} else {
				// Probably not a number (string/char)
//				if ($type === 'string' /*&& $column->getFixed()*/) {//FIXME
//					$type = 'char';
//				}
				$args = $this->getLength($length);
			}

			if ($nullable) $decorators[] = 'nullable';
			if ($default !== null) $decorators[] = $this->getDefault($default, $type);
			if ($index) $decorators[] = $this->decorate($index->type, $index->name);

			$field = ['field' => $name, 'type' => $type];
			if ($decorators) $field['decorators'] = $decorators;
			if ($args) $field['args'] = $args;
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
		if ($length and $length !== 255) {
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
			if ($type == 'dateTime')
				$type = 'timestamp';
			$default = $this->decorate('DB::raw', $default);
		} elseif (in_array($type, ['string', 'text']) or !is_numeric($default)) {
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
	 * @param string       $quotes
	 * @return string
	 */
	protected function argsToString($args, $quotes = '\'')
	{
		if ( is_array( $args ) ) {
			$seperator = $quotes .', '. $quotes;
			$args = implode( $seperator, $args );
		}

		return $quotes . $args . $quotes;
	}

	/**
	 * Get Decorator
	 * @param string       $function
	 * @param string|array $args
	 * @param string       $quotes
	 * @return string
	 */
	protected function decorate($function, $args, $quotes = '\'')
	{
		if ( ! is_null( $args ) ) {
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
				'field' => $index['columns'],//$index->columns,
				'type' => $index['type'],
			];
			if ($index['name']) {
				$indexArray['args'] = $this->argsToString($index['name']);
			}
			$indexes[] = $indexArray;
		}
		return $indexes;
	}
}

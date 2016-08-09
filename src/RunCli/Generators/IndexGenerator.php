<?php
namespace RunCli\Generators;

class IndexGenerator {

	/**
	 * @var array
	 */
	protected $indexes=[];

  /**
   * @var array List all indexes
   */
  protected $indexesList=[];

	/**
	 * @var array
	 */
	protected $multiFieldIndexes=[];

	/**
	 * @var bool
	 */
	private $ignoreIndexNames;

	/**
	 * @param string                                      $table Table Name
	 * @param \RunCli\Generators\SchemaGenerator $schema
	 * @param bool                                        $ignoreIndexNames
	 */
	public function __construct($table, $schema, $ignoreIndexNames)
	{
		$this->indexes = $this->multiFieldIndexes = [];
		$this->ignoreIndexNames = $ignoreIndexNames;

		$this->indexesList = $schema->listTableIndexes( $table );
//die(print_r($this->indexesList));
		foreach ( $this->indexesList as $index ) {
//die(print_r($index));
			$indexArray = $this->indexToArray($table, $index);
//die(print_r($indexArray));
			if ( count( $indexArray['columns'] ) === 1 ) {
				$columnName = $indexArray['columns'][0];
//die(print_r($indexArray));
				$this->indexes[ $columnName ] = /*(object)*/ $indexArray;
			} else {
        if(in_array($indexArray, $this->multiFieldIndexes))
        {
          continue;
        }
				$this->multiFieldIndexes[] = /*(object)*/ $indexArray;
			}
		}
//    array_unique ($this->multiFieldIndexes);
//die(print_r($tmp));
//die(print_r($this->indexes));
//die(print_r($this->multiFieldIndexes));
	}


  protected function compareStr($str, $needle)
  {
    if(stripos($str, $needle) === false){
      return false;
    }else{
      return true;
    }
  }
  protected function getMultiple($key)
  {
    $ret = [];
    foreach ($this->indexesList as $item) {
      if($item->Key_name === $key->Key_name)
      {
        $ret[] = $item->Column_name;
      }
    }
    return $ret;
  }
	protected function indexToArray($table, $index)
	{
//die(print_r($index));
    //if ( $index->isPrimary() ) {
		if ( $this->compareStr($index->Key_name, 'PRIMARY') ) {
			$type = 'primary';
    //} elseif ( $index->isUnique() ) {
		} elseif ( $index->Non_unique === 0 ) {
			$type = 'unique';
		} else {
			$type = 'index';
		}
		$array = ['type' => $type, 'name' => null, 'columns' => $this->getMultiple($index)/*getColumns()*/];
//die(print_r($array));
		if ( ! $this->ignoreIndexNames and
      !$this->isDefaultIndexName(
        $table,
        $index->Key_name,//getName()
        $type,
        $this->getMultiple($index)//getColumns()
        )
    ) {
			$array['name'] = $index->Key_name;//getName();
		}
//die(print_r($array));
		return $array;
	}

	/**
	 * @param string $table Table Name
	 * @param string $type Index Type
	 * @param string|array $columns Column Names
	 * @return string
	 */
	protected function getDefaultIndexName( $table, $type, $columns )
	{
		if ($type=='primary') {
			return 'PRIMARY';
		}
		if ( is_array( $columns ) ) {
			$columns = implode( '_', $columns );
		}
		return $table .'_'. $columns .'_'. $type;
	}

	/**
	 * @param string       $table   Table Name
	 * @param string       $name    Current Name
	 * @param string       $type    Index Type
	 * @param string|array $columns Column Names
	 * @return bool
	 */
	protected function isDefaultIndexName( $table, $name, $type, $columns )
	{
		return $name == $this->getDefaultIndexName( $table, $type, $columns );
	}


	/**
	 * @param string $name
	 * @return null|object
	 */
	public function getIndex($name)
	{
		if ( isset( $this->indexes[ $name ] ) ) {
			return (object) $this->indexes[ $name ];
		}
		return null;
	}

	/**
	 * @return null|object
	 */
	public function getMultiFieldIndexes()
	{
		return $this->multiFieldIndexes;
	}

}

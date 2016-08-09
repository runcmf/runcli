<?php namespace RunCli\Generators;

use Illuminate\Database\Capsule\Manager as DB;

class SchemaGenerator {

	/**
	 * @var schema
	 */
	protected $schema;

	/**
	 * @var FieldGenerator
	 */
	protected $fieldGenerator;

	/**
	 * @var ForeignKeyGenerator
	 */
	protected $foreignKeyGenerator;

	/**
	 * @var string
	 */
	protected $database;
	/**
	 * @var bool
	 */
	private $ignoreIndexNames;
	/**
	 * @var bool
	 */
	private $ignoreForeignKeyNames;

	/**
	 * @param string $database
	 * @param bool   $ignoreIndexNames
	 * @param bool   $ignoreForeignKeyNames
	 */
	public function __construct($database, $ignoreIndexNames, $ignoreForeignKeyNames)
	{
		$this->database = $database;

		$this->fieldGenerator = new FieldGenerator();
		$this->foreignKeyGenerator = new ForeignKeyGenerator();

		$this->ignoreIndexNames = $ignoreIndexNames;
		$this->ignoreForeignKeyNames = $ignoreForeignKeyNames;
	}

	/**
	 * @return mixed
	 */
	public function getTables()
	{
		return $this->listTableNames();
	}

	public function getFields($table)
	{
    return $this->fieldGenerator->generate($table, $this, $this->database, $this->ignoreIndexNames);
	}

	public function getForeignKeyConstraints($table)
	{
    return $this->foreignKeyGenerator->generate($this->database, $table, $this, $this->ignoreForeignKeyNames);
	}

  /**
   * Get all the tables
   * @return mixed
   */
  protected function listTableNames()
  {
    $q = 'SELECT table_name FROM information_schema.tables WHERE table_schema = "'.$this->database.'"';
    return DB::select($q);
  }

	public function getEnum($table)
  {
//    $result = DB::table('information_schema.columns')//FIXME with prefix table bug
//      ->where('table_schema', $this->database)
//      ->where('table_name', $table)
//      ->where('data_type', 'enum')
//      ->get(['column_name','column_type']);
    $q = 'select `column_name`, `column_type` 
    from `information_schema`.`columns` 
    where `table_schema` = "'.$this->database.'" 
    and `table_name` = "'.$table.'" 
    and `data_type` = "enum"';
    return DB::select(DB::raw($q));
  }

  public function listTableColumns($table)
  {
//        $res =  DB::table('information_schema.columns')//FIXME with prefix table bug
//            ->where('table_schema', '=', $this->database)
//            ->where('table_name', '=', $table)
//            ->get($this->selects);

    $q = 'select *
      from `information_schema`.`columns`
      where `table_schema` = "'.$this->database.'" and `table_name` = "'.$table.'"';

    $res = DB::select(DB::raw($q));
    return $res;
  }

  public function listTableIndexes($table)
  {
    $q = 'SHOW INDEX FROM ' . $table;
    return DB::select(DB::raw($q));
  }

  public function listTableForeignKeys($table, $database)
  {
    $q = $this->getListTableForeignKeysSQL($table, $database);
    return DB::select(DB::raw($q));
  }

  /**
   * function from Doctrine\DBAL\Platforms  MySqlPlatform
   *
   * @param $table
   * @param null $database
   * @return string
   */
  public function getListTableForeignKeysSQL($table, $database = null)
  {
    $sql = "SELECT DISTINCT k.`CONSTRAINT_NAME`, k.`COLUMN_NAME`, k.`REFERENCED_TABLE_NAME`, ".
      "k.`REFERENCED_COLUMN_NAME` /*!50116 , c.update_rule, c.delete_rule */ ".
      "FROM information_schema.key_column_usage k /*!50116 ".
      "INNER JOIN information_schema.referential_constraints c ON ".
      "  c.constraint_name = k.constraint_name AND ".
      "  c.table_name = '$table' */ WHERE k.table_name = '$table'";

    if ($database) {
      $sql .= " AND k.table_schema = '$database' /*!50116 AND c.constraint_schema = '$database' */";
    }

    $sql .= " AND k.`REFERENCED_COLUMN_NAME` is not NULL";

    return $sql;
  }
}

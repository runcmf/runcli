<?php namespace RunCli\Generators;

use Illuminate\Database\Capsule\Manager as DB;

class SchemaGenerator
{
    /**
     * @var schema
     */
    protected $schema;

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

    private $adapter;
    private $cfg;

    /**
     * @param array $cfg DB config
     * @param string $database
     * @param string $ignoreIndexNames
     * @param string $ignoreForeignKeyNames
     */
    public function __construct($cfg, $database = '', $ignoreIndexNames = '', $ignoreForeignKeyNames = '')
    {
        $this->cfg = $cfg;

        if (!$database) {
            $this->database = $cfg['database'];
        } else {
            $this->database = $database;
        }

        $this->ignoreIndexNames = $ignoreIndexNames;
        $this->ignoreForeignKeyNames = $ignoreForeignKeyNames;

        switch ($cfg['driver']) {
            case 'pgsql':
                $this->adapter = new \RunCli\Adapter\PgSql();
                break;
            case 'mysql':
                $this->adapter = new \RunCli\Adapter\MySql();
                break;
            case 'sqlite':
                $this->adapter = new \RunCli\Adapter\SqlLite();
                break;
            case 'sqlsrv':
                $this->adapter = new \RunCli\Adapter\SqlSrv();
                break;
            default:
                throw new \Exception('Database driver not supported: ' . $cfg['driver']);
                break;
        }
    }

    public function getFields($table)
    {
        return (new FieldGenerator())->generate($table, $this, $this->database, $this->ignoreIndexNames);
    }

    public function getForeignKeyConstraints($table)
    {
        return (new ForeignKeyGenerator())->generate($this->database, $table, $this, $this->ignoreForeignKeyNames);
    }

    public function getTablePrefix()
    {
        return DB::connection()->getConfig('prefix');
    }

    /**
     * Checks if a database table exists
     * @param string $table
     * @return boolean
     */
    public function hasTable($table)
    {
        return $this->adapter->hasTable($table);
    }

    public function getData($table, $max)
    {
        DB::connection()->setTablePrefix('');
        if (!$max) {
            return DB::table($table)->get()->toArray();
        }

        return DB::table($table)->limit($max)->get()->toArray();
    }

    /**
     * Get all the tables
     * @return mixed
     */
    public function listTableNames()
    {
        return $this->adapter->listTableNames($this->database);
    }

    public function getEnum($table)
    {
        return $this->adapter->getEnum($table, $this->database);
    }


    public function listTableColumns($table)
    {
        return $this->adapter->listTableColumns($table, $this->database);
    }

    public function listTableIndexes($table)
    {
        return $this->adapter->listTableIndexes($table, $this->database);
    }

    public function listTableForeignKeys($table, $database)
    {
        return $this->adapter->listTableForeignKeys($table, $database);
    }

    public function createDatabase($schemaName, $charset, $collation)
    {
        return $this->adapter->createDatabase($schemaName, $charset, $collation, $this->cfg);
    }
}

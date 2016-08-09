<?php namespace RunCli\Command;

use RunCli\CliTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Illuminate\Database\Capsule\Manager as DB;

use RunCli\Generators\SchemaGenerator;
use RunCli\Syntax\AddForeignKeysToTable;
use RunCli\Syntax\AddToTable;
use RunCli\Syntax\DroppedTable;
use RunCli\Syntax\RemoveForeignKeysFromTable;

use Exception;

class MigrationsGeneratorCommand extends Command
{
  use CliTrait;

  protected $schema;

  protected function configure()
  {
    $this
      ->setName('make:generate')
      ->setDescription('Generate migrations from existing DB.')
      ->addArgument(
        'path',
        InputArgument::OPTIONAL,
        'Path where migrations will be saved. WARNING - remember file permissions!'
      )
      ->addArgument(
        'database',
        InputArgument::OPTIONAL,
        'Override the application database'
      )
      ->addArgument(
        'ignoreFK',
        InputArgument::OPTIONAL,
        'Comma separated list ignored foreign keys names'
      )
      ->addOption(
        'ignore', 'ign', InputOption::VALUE_REQUIRED, 'Ignore tables to export, seperated by a comma', null
      )
      ->setHelp(<<<EOT
Hardcoded path prefix 'DIR' - is path to root with slash and suffix '/var/migrations'.
What in the middle is optional and you can set it or no.
For example command <info>php cli make:generate</info>
try generate migrations to your_root/var/migrations
Command <info>php cli make:migrate vendor/runcmf/runbb</info>
try generate migrations to your_root/<comment>vendor/runcmf/runbb</comment>/var/migrations
<error>REMEMBER</error> <question>directory must be writable</question>
EOT
);
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->initDB();

    $this->input = $input;
    $this->output = $output;
    $this->dialog = $this->getHelper('question');

    $path = $this->input->getArgument('path');

    $this->setMigrationPath($path);

    $database = $this->input->getArgument('database');
    $ignoreFKNames = $this->input->getArgument('ignoreFK');
    $ignore = $this->input->getOption('ignore');

    // Display some helpfull info
    if (empty($database)) {
      $database = $this->getDatabaseName();
    }
    $this->output->writeln('<fg=blue>Preparing migrations from database:</> <fg=red;options=bold>' .$database . '</>');

    if (!empty($ignore)) {
//      $tables = explode(',', str_replace(' ', '', $ignore));
      foreach ($ignore as $table) {
        $this->output->writeln('<comment>Ignoring the '.$table.' table</comment>');
      }
    }

    $this->schema = new SchemaGenerator(
      $database,
      $ignore,
      $ignoreFKNames
    );

    $tables = $this->schema->getTables();

    $this->sectionMessage('1', '<fg=magenta>Setting up Tables and Index Migrations</>');
    $this->generate( 'create', $tables );

    $this->sectionMessage('2', '<fg=magenta>Setting up Foreign Key Migrations</>');
    $this->generate( 'foreign_keys', $tables );

    // Symfony style block messages
    $this->blockMessage('Success!', 'Database migrations generated in: '.$this->getMigrationPath());
  }


  /**
   * Generate Migrations
   *
   * @param  string $method Create Tables or Foreign Keys ['create', 'foreign_keys']
   * @param  array  $tables List of tables to create migrations for
   * @throws Exception
   * @return void
   */
  private function generate( $method, $tables )
  {
    if (!empty($tables)) {
      $this->customDb = true;
    }

    if ( $method == 'create' ) {
      $function = 'getFields';
      $prefix = 'create';
      $this->datePrefix = date( 'Y_m_d_His' );
    } elseif ( $method = 'foreign_keys' ) {
      $function = 'getForeignKeyConstraints';
      $prefix = 'add_foreign_keys_to';
      $method = 'table';
      $this->datePrefix = date( 'Y_m_d_His', strtotime( '+1 second' ) );
    } else {
      throw new \Exception( $method );
    }
    $cnt=0;
    foreach ( $tables as $table ) {
//die(print_r($table));
      $cnt++;
      $table = $table->table_name;
      $this->migrationName = $prefix .'_'. $table .'_table';
      $this->fileName = $this->datePrefix .'_'.$prefix .'_'. $table .'_table.php';
      $this->method = $method;
      $this->table = $table;
      $this->fields = $this->schema->{$function}( $table );
      if ( $this->fields ) {
        $this->save($this->fileName);
        //$this->output->writeln('<comment>Migration ' . $table . '</comment>');
//if($cnt === 3) {
//  die($this->migrationName."\n");
//}

//        if ( $this->log ) {
//          $file = $this->datePrefix . '_' . $this->migrationName;
//          $this->repository->log($file, $this->batch);
//        }
      }
    }
  }

  /**
   * Compile and generate the file.
   */
  private function save($file)
  {
    if(!is_dir($this->getMigrationPath()) || !is_writable($this->getMigrationPath())){
      throw new \Exception( $this->getMigrationPath().' path not found!' );
    }

    try
    {
      $template = $this->compile(
        'migration',
        $this->getTemplateData()
      );
      file_put_contents($this->getMigrationPath() . '/' . $file, $template);

      $this->output->writeln('<comment>Generated migration:</comment> <fg=cyan;options=bold>' . $file . '</>');
    }

    catch (Exception $e)
    {
      $this->output->writeln('<error>The file, ' . $file . ', already exists! I don\'t want to overwrite it.</error>');
    }
  }

  /**
   * Fetch the template data
   *
   * @return array
   */
  private function getTemplateData()
  {
    $table = substr($this->table, strlen(DB::getTablePrefix()));

    if ( $this->method == 'create' ) {
      $up = (new AddToTable($this->file))->run($this->fields, $table, 'create');
      $down = (new DroppedTable)->drop($table);
    } else {
      $up = (new AddForeignKeysToTable($this->file, $this->compiler))->run($this->fields,$table);
      $down = (new RemoveForeignKeysFromTable($this->file, $this->compiler))->run($this->fields,$table);
    }

    return [
      'CLASS' => ucwords(camel_case($this->migrationName)),
      'UP'    => $up,
      'DOWN'  => $down
    ];
  }

}
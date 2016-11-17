<?php namespace RunCli\Command;

use RunCli\CliTrait;
use RunCli\Generators\SchemaGenerator;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use Exception;

class SeedGeneratorCommand extends Command
{
    use CliTrait;

    /**
     * New line character for seed files.
     * Double quotes are mandatory!
     *
     * @var string
     */
    private $newLineCharacter = "\r\n";

    /**
     * Desired indent for the code.
     * For tabulator use \t
     * Double quotes are mandatory!
     *
     * @var string
     */
    private $indentCharacter = '    ';
    private $schema;
    private $chunk_size = 500; // Maximum number of rows per insert statement

    protected function configure()
    {
        $this
            ->setName('seed:generate')
            ->setDescription('Generate your database table data to a seeds class to given path. f.ex. vendor/runcmf/runbb')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Set Path Seeds Module Root Dir without trailing slash. f.ex. vendor/runcmf/runbb'
            )
            ->addOption('ignore', 'ign', InputOption::VALUE_NONE, 'Ignore tables to export, seperated by a comma', null)
            ->addOption('clean', null, InputOption::VALUE_NONE, 'clean iseed section', null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'force overwrite of all existing seed classes', null)
            ->addOption('database', null, InputOption::VALUE_OPTIONAL, 'database connection', null)
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'max number of rows', null)
            ->addOption('prerun', null, InputOption::VALUE_OPTIONAL, 'prerun event name', null)
            ->addOption('postrun', null, InputOption::VALUE_OPTIONAL, 'postrun event name', null)
            ->setHelp(<<<EOT
Hardcoded path prefix 'DIR' - is path to root with slash and suffix '/var/seeds'.
What in the middle is optional and you can set it or no.
For example command <info>php cli seed:generate</info>
try generate seeds to your_root/var/seeds
Command <info>php cli seed:generate vendor/runcmf/runbb</info>
try generate seeds to your_root/<comment>vendor/runcmf/runbb</comment>/var/seeds
<error>REMEMBER</error> <question>directory must be writable</question>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initDB();
        $this->input = $input;
        $this->output = $output;
        $helper = $this->getHelper('question');

        $this->setSeedPath($input->getArgument('path'));
        $database = $this->input->getOption('database');
        if (empty($database)) {
            $database = $this->getDatabaseName();
        }

        $_driver = $this->cfg['settings']['db']['default'];
        $this->schema = new SchemaGenerator($this->cfg['settings']['db']['connections'][$_driver], $database);

        $this->output->writeln('<fg=blue>Preparing seeders classes from database:</> <fg=red;options=bold>' . $this->getDatabaseName() . '</>');

//    $ignore = $this->input->getOption('ignore');

        // if clean option is checked empty iSeed template in DatabaseSeeder.php
        if ($this->input->getOption('clean')) {
            $this->cleanSection();
        }

//    $tables        = explode(',', $this->input->getArgument('tables'));
        $tables = $this->schema->listTableNames();
        $chunkSize = (int)($this->input->getOption('max'));
        $prerunEvents = explode(',', $this->input->getOption('prerun'));
        $postrunEvents = explode(',', $this->input->getOption('postrun'));

        $chunkSize = ($chunkSize < 1) ? null : $chunkSize;

        $tableIncrement = 0;
        foreach ($tables as $table) {
            $table = trim($table->table_name);
//      $table = str_replace($this->schema->getTablePrefix(),'',$table);
            $prerunEvent = null;
            if (isset($prerunEvents[$tableIncrement])) {
                $prerunEvent = trim($prerunEvents[$tableIncrement]);
            }
            $postrunEvent = null;
            if (isset($postrunEvents[$tableIncrement])) {
                $postrunEvent = trim($postrunEvents[$tableIncrement]);
            }
            $tableIncrement++;

            // generate file and class name based on name of the table
            $fileName = $this->generateFileName($table);

            // if file does not exist or force option is turned on generate seeder
            if (!$this->fileExists($fileName) || $this->input->getOption('force')) {
                $this->generateSeed($table, $database, $chunkSize, $prerunEvent, $postrunEvent);
                continue;
            }

            $question = new ConfirmationQuestion('File ' . $fileName . ' already exist. Do you wish to override it? [yes[y]|no[n]]', false);
            if ($helper->ask($input, $output, $question)) {
                // if user said yes overwrite old seeder
                $this->generateSeed($table, $database, $chunkSize, $prerunEvent, $postrunEvent);
            }
        }

        $this->blockMessage('Success!', $tableIncrement . ' seeds generated in: ' . $this->getSeedPath());
    }

    public function generateSeed($table, $database = null, $max = 0, $prerunEvent = null, $postrunEvent = null)
    {
        // Check if table exists
        if (!$this->schema->hasTable($table)) {
            throw new Exception("Table $table was not found.");
        }

        // Get the data
        $data = $this->schema->getData($table, $max);
        // Repack the data
        $dataArray = $this->repackSeedData($data);
        // Generate file name
        $fname = $this->generateFileName($table);
        // Generate class name
        $className = $this->mapFileNameToClassName($fname);
        // Get template for a seed file contents
        $stub = $this->fileGet($this->getTemplate('seed'));
        // Get a populated stub file
        $seedContent = $this->populateStub(
            $className,
            $stub,
            str_replace($this->schema->getTablePrefix(), '', $table),
//      $table,
            $dataArray,
            null,
            $prerunEvent,
            $postrunEvent
        );
        // Save a populated stub
        $this->fileSave($this->getSeedPath() . '/' . $fname, $seedContent);
        $this->output->writeln('<comment>Generated seed:</comment> <fg=cyan;options=bold>' . $fname . '</>');

        // Update the DatabaseSeeder.php file
        //return $this->updateDatabaseSeederRunMethod($className) !== false;
    }

    /**
     * Repacks data read from the database
     * @param  array|object $data
     * @return array
     */
    public function repackSeedData($data)
    {
        $dataArray = [];
        if (is_array($data)) {
            foreach ($data as $row) {
                $rowArray = [];
                foreach ($row as $columnName => $columnValue) {
                    $rowArray[$columnName] = $columnValue;
                }
                $dataArray[] = $rowArray;
            }
        }
        return $dataArray;
    }


    /**
     * Populate the place-holders in the seed stub.
     * @param  string $class
     * @param  string $stub
     * @param  string $table
     * @param  string $data
     * @param  int $chunkSize
     * @param  string $prerunEvent
     * @param  string $postunEvent
     * @return string
     */
    public function populateStub($class, $stub, $table, $data, $chunkSize = null, $prerunEvent = null, $postrunEvent = null)
    {
        $chunkSize = $chunkSize ?: $this->chunk_size;
        $inserts = '';
        $chunks = array_chunk($data, $chunkSize);
        foreach ($chunks as $chunk) {
            $this->addNewLines($inserts);
            $this->addIndent($inserts, 2);
            $inserts .= sprintf(
                "DB::table('%s')->insert(%s);",
                $table,
                $this->prettifyArray($chunk)
            );
        }

        $stub = str_replace('{{class}}', $class, $stub);

        $prerunEventInsert = '';
        if ($prerunEvent) {
            $prerunEventInsert .= "\$response = Event::until(new $prerunEvent());";
            $this->addNewLines($prerunEventInsert);
            $this->addIndent($prerunEventInsert, 2);
            $prerunEventInsert .= 'if ($response === false) {';
            $this->addNewLines($prerunEventInsert);
            $this->addIndent($prerunEventInsert, 3);
            $prerunEventInsert .= 'throw new Exception("Prerun event failed, seed wasn\'t executed!");';
            $this->addNewLines($prerunEventInsert);
            $this->addIndent($prerunEventInsert, 2);
            $prerunEventInsert .= '}';
        }

        $stub = str_replace('{{prerun_event}}', $prerunEventInsert, $stub);

        if (!is_null($table)) {
            $stub = str_replace('{{table}}', $table, $stub);
        }

        $postrunEventInsert = '';
        if ($postrunEvent) {
            $postrunEventInsert .= "\$response = Event::until(new $postrunEvent());";
            $this->addNewLines($postrunEventInsert);
            $this->addIndent($postrunEventInsert, 2);
            $postrunEventInsert .= 'if ($response === false) {';
            $this->addNewLines($postrunEventInsert);
            $this->addIndent($postrunEventInsert, 3);
            $postrunEventInsert .= 'throw new Exception("Seed was executed but the postrun event failed!");';
            $this->addNewLines($postrunEventInsert);
            $this->addIndent($postrunEventInsert, 2);
            $postrunEventInsert .= '}';
        }

        $stub = str_replace('{{postrun_event}}', $postrunEventInsert, $stub);

        $stub = str_replace('{{insert_statements}}', $inserts, $stub);

        return $stub;
    }

    /**
     * Prettify a var_export of an array
     * @param  array $array
     * @return string
     */
    protected function prettifyArray($array)
    {
        $content = var_export($array, true);
        $lines = explode("\n", $content);

        $inString = false;
        $tabCount = 3;
        for ($i = 1; $i < count($lines); $i++) {
            $lines[$i] = ltrim($lines[$i]);

            //Check for closing bracket
            if (strpos($lines[$i], ')') !== false) {
                $tabCount--;
            }

            //Insert tab count
            if ($inString === false) {
                for ($j = 0; $j < $tabCount; $j++) {
                    $lines[$i] = substr_replace($lines[$i], $this->indentCharacter, 0, 0);
                }
            }

            for ($j = 0; $j < strlen($lines[$i]); $j++) {
                //skip character right after an escape \
                if ($lines[$i][$j] == '\\') {
                    $j++;
                } //check string open/end
                else if ($lines[$i][$j] == '\'') {
                    $inString = !$inString;
                }
            }

            //check for openning bracket
            if (strpos($lines[$i], '(') !== false) {
                $tabCount++;
            }
        }

        $content = implode("\n", $lines);

        return $content;
    }

    /**
     * Adds new lines to the passed content variable reference.
     *
     * @param string $content
     * @param int $numberOfLines
     */
    private function addNewLines(&$content, $numberOfLines = 1)
    {
        while ($numberOfLines > 0) {
            $content .= $this->newLineCharacter;
            $numberOfLines--;
        }
    }

    /**
     * Adds indentation to the passed content reference.
     *
     * @param string $content
     * @param int $numberOfIndents
     */
    private function addIndent(&$content, $numberOfIndents = 1)
    {
        while ($numberOfIndents > 0) {
            $content .= $this->indentCharacter;
            $numberOfIndents--;
        }
    }

    /**
     * Cleans the iSeed section
     * @return bool
     */
    public function cleanSection()
    {
        $databaseSeederPath = $this->getSeedPath() . '/DatabaseSeeder.php';

        $content = $this->fileGet($databaseSeederPath);

        $content = preg_replace("/(\#iseed_start.+?)\#iseed_end/us", "#iseed_start\n\t\t#iseed_end", $content);

        return $this->fileSave($databaseSeederPath, $content) !== false;
        //return false;
    }

    /**
     * Updates the DatabaseSeeder file's run method (kudoz to: https://github.com/JeffreyWay/Laravel-4-Generators)
     * @param  string $className
     * @return bool
     */
    public function updateDatabaseSeederRunMethod($className)
    {
        $databaseSeederPath = $this->getSeedPath() . '/DatabaseSeeder.php';

        $content = $this->fileGet($databaseSeederPath);//$this->files->get($databaseSeederPath);
        if (strpos($content, "\$this->call('{$className}')") === false) {
            if (
                strpos($content, '#iseed_start') &&
                strpos($content, '#iseed_end') &&
                strpos($content, '#iseed_start') < strpos($content, '#iseed_end')
            ) {
                $content = preg_replace("/(\#iseed_start.+?)(\#iseed_end)/us", "$1\$this->call('{$className}');{$this->newLineCharacter}{$this->indentCharacter}{$this->indentCharacter}$2", $content);
            } else {
                $content = preg_replace("/(run\(\).+?)}/us", "$1{$this->indentCharacter}\$this->call('{$className}');{$this->newLineCharacter}{$this->indentCharacter}}", $content);
            }
        }

        return $this->fileSave($databaseSeederPath, $content) !== false;
    }

    private function generateFileName($table)
    {
        if (!$this->schema->hasTable($table)) {
            throw new Exception('Table ' . $table . ' was not found.');
        }
        $table = str_replace($this->schema->getTablePrefix(), '', $table);
        return date($this->dateTemplate) . '_' . $table . '_table_seeds.php';
    }
}
<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
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

namespace RunCli\Command;

use RunCli\CliTrait;
use RunCli\Generators\SchemaGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModelGeneratorCommand extends Command
{
    use CliTrait;

    private $namespace;
    private $schema;

    protected function configure()
    {
        $this
            ->setName('model:generate')
            ->setDescription('Generate Eloquent models from an existing table structure. 
            to given path. f.ex. vendor/runcmf/runbb')
            ->addArgument(
                'tables',
                InputArgument::OPTIONAL,
                'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'
            )
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Set Path Model Module Root Dir without trailing slash. f.ex. vendor/runcmf/runbb'
            )
            ->addOption(
                'connection',
                'c',
                InputOption::VALUE_NONE,
                'The database connection to use.',
                $this->dbDefault,
                null
            )
            ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'database connection', null)
            ->addOption('tables', 't', InputOption::VALUE_NONE, 'A list of Tables you wish to Generate Migrations 
            for separated by a comma: users,posts,comments', null)
            ->addOption('path', 'p', InputOption::VALUE_NONE, 'Where should the file be created?', null)
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Explicitly set the namespace', null)
            ->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'Overwrite existing models ?', null)
            ->setHelp(<<<EOT
Hardcoded path prefix 'DIR' - is path to root with slash and suffix '/var/models'.
What in the middle is optional and you can set it or no.
For example command <info>php bin/cli model:generate</info>
try generate modelss to your_root/var/models
Command <info>php bin/cli model:generate vendor/runcmf/runbb</info>
try generate models to your_root/<comment>vendor/runcmf/runbb</comment>/var/models
<error>REMEMBER</error> <question>directory must be writable</question>
Example:
php bin/cli model:generate --namespace='YourProject\Models'
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initDB();

        $this->input = $input;
        $this->output = $output;
        // init helper
        $this->getHelper('question');
        $this->output->writeln('<info>Let\'s begin...</info>');
        // determine destination folder
        $this->setModelPath($input->getArgument('path'));
        $destinationFolder = $this->getModelPath();
        $this->output->writeln(
            '<comment>Set destination Folder:</comment> <fg=cyan;options=bold>'.$destinationFolder.'</>'
        );

        $database = $this->input->getOption('database');
        if (empty($database)) {
            $database = $this->getDatabaseName();
        }
        $_driver = $this->cfg['settings']['db']['default'];
        $this->schema = new SchemaGenerator($this->cfg['settings']['db']['connections'][$_driver], $database);
        $this->output->writeln('<info>Init schema...</info>');

        // set namespace
        $this->namespace = $this->getNamespace();
        // fetch all tables
        $this->output->writeln('<info>Fetching tables...</info>');
        $tables = $this->getTables();

        // for each table, fetch primary and foreign keys
        $this->output->writeln('<info>Fetching table columns, primary keys, foreign keys</info>');
        $prep = $this->getColumnsPrimaryAndForeignKeysPerTable($tables);

        // create an array of rules, holding the info for our Eloquent models to be
        $this->output->writeln('<info>Generating Eloquent rules</info>');
        $eloquentRules = $this->getEloquentRules($tables, $prep);

        // Generate our Eloquent Models
        $this->output->writeln('<info>Generating Eloquent models</info>');

        $this->generateEloquentModels($destinationFolder, $eloquentRules);

        $this->output->writeln('<comment>All</comment> <fg=cyan;options=bold>done!</>');
    }

    public function getTables()
    {
        $schemaTables = $this->schema->listTableNames();
        $specifiedTables = $this->input->getOption('tables');

        //when no tables specified, generate all tables
        if (empty($specifiedTables)) {
            return $schemaTables;
        }

        $specifiedTables = explode(',', $specifiedTables);

        $tablesToGenerate = [];
        foreach ($specifiedTables as $specifiedTable) {
            if (!in_array($specifiedTable, $schemaTables)) {
                $this->output->writeln('<error>specified table not found: '. $specifiedTable.'</error>');
            } else {
                $tablesToGenerate[$specifiedTable] = $specifiedTable;
            }
        }

        if (empty($tablesToGenerate)) {
            $this->output->writeln('<error>No tables to generate</error>');
            die;
        }

        return array_values($tablesToGenerate);
    }
/*
    // FIXME temporary !!!
    private function clearPrefix($name)
    {
        $prefixes = [
            'mybb_',
            'fbb_'
        ];
        foreach ($prefixes as $prefix) {
            $name = str_replace($prefix, '', $name);
        }
        return $name;
    }
*/
    private function generateEloquentModels($destinationFolder, $eloquentRules)
    {
        foreach ($eloquentRules as $table => $rules) {
//            $table = $this->clearPrefix($table);
            $table = str_replace($this->schema->getTablePrefix(), '', $table);
            try {
                $this->generateEloquentModel($destinationFolder, $table, $rules);
            } catch (\Exception $e) {
                $this->output->writeln('<error>Failed to generate model for table'. $table.'</error>');
                return;
            }
        }
    }

    private function generateEloquentModel($destinationFolder, $table, $rules)
    {

        //1. Determine path where the file should be generated
        $modelName = $this->generateModelNameFromTableName($table);
        $filePathToGenerate = $destinationFolder . '/'.$modelName.'.php';

        $canContinue = $this->canGenerateEloquentModel($filePathToGenerate, $table);
        if (!$canContinue) {
            return;
        }

        //2.  generate relationship functions and fillable array
        $hasMany = $rules['hasMany'];
        $hasOne = $rules['hasOne'];
        $belongsTo = $rules['belongsTo'];
        $belongsToMany = $rules['belongsToMany'];

//        $fillable = implode(', ', $rules['fillable']);
        $fillable = implode(",\n            ", $rules['fillable']);

        $belongsToFunctions = $this->generateBelongsToFunctions($belongsTo);
        $belongsToManyFunctions = $this->generateBelongsToManyFunctions($belongsToMany);
        $hasManyFunctions = $this->generateHasManyFunctions($hasMany);
        $hasOneFunctions = $this->generateHasOneFunctions($hasOne);

        $functions = $this->generateFunctions([
            $belongsToFunctions,
            $belongsToManyFunctions,
            $hasManyFunctions,
            $hasOneFunctions,
        ]);

        // set commented line
        $k = '//protected $primaryKey = \'\';// NO PRIMARY KEY DEFINED';
        if (is_array($rules['primaryKey'])) {
            if (count($rules['primaryKey']) > 1) {
                // set composite key
                $k = 'protected $primaryKey = '."['".implode("', '", $rules['primaryKey'])."'];";
            } else {
                // set single key
                $k = 'protected $primaryKey = \''.$rules['primaryKey'][0].'\';';
            }
        }

        //3. prepare template data
        $templateData = [
            'NAMESPACE' => $this->namespace,
            'NAME' => $modelName,
            'TABLENAME' => $table,
            'FILLABLE' => $fillable,
            'FUNCTIONS' => $functions,
            'PRIMARYKEY' => $k,
            'TIMESTAMP' => var_export($rules['timestamps'], true)
        ];

        $this->make(
            $filePathToGenerate,
            $this->compile(
                'model',
                $templateData
            )
        );

        $this->output->writeln('<info>Generated model for table '.$table.'</info>');
    }

    private function canGenerateEloquentModel($filePathToGenerate, $table)
    {
        $canOverWrite = $this->input->getOption('overwrite');
        if (file_exists($filePathToGenerate)) {
            if ($canOverWrite) {
                $deleted = unlink($filePathToGenerate);
                if (!$deleted) {
                    $this->output->writeln(
                        '<question>Failed to delete existing model '.$filePathToGenerate.'</question>'
                    );
                    return false;
                }
            } else {
                $this->output->writeln(
                    "<question>Skipped model generation, file already exists. (force using --overwrite) 
                    $table -> $filePathToGenerate</question>"
                );
                return false;
            }
        }

        return true;
    }

    private function getNamespace()
    {
        $ns = $this->input->getOption('namespace');
        if (empty($ns)) {
//            $ns = env('APP_NAME','App\Models');
            $ns = 'App\Models';
        }
        //convert forward slashes in the namespace to backslashes
        $ns = str_replace('/', '\\', $ns);
        return $ns;
    }

    private function generateFunctions($functionsContainer)
    {
        $f = '';
        foreach ($functionsContainer as $functions) {
            $f .= $functions;
        }

        return $f;
    }

    private function generateHasManyFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasManyModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasManyFunctionName = $this->getPluralFunctionName($hasManyModel);

            $function = "
    public function $hasManyFunctionName() {".'
        return $this->hasMany'."(\\".$this->namespace."\\$hasManyModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateHasOneFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasOneModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasOneFunctionName = $this->getSingularFunctionName($hasOneModel);

            $function = "
    public function $hasOneFunctionName() {".'
        return $this->hasOne'."(\\".$this->namespace."\\$hasOneModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateBelongsToFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $belongsToFunctionName = $this->getSingularFunctionName($belongsToModel);

            $function = "
    public function $belongsToFunctionName() {".'
        return $this->belongsTo'."(\\".$this->namespace."\\$belongsToModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateBelongsToManyFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToManyModel = $this->generateModelNameFromTableName($rules[0]);
            $through = $rules[1];
            $key1 = $rules[2];
            $key2 = $rules[3];

            $belongsToManyFunctionName = $this->getPluralFunctionName($belongsToManyModel);

            // @codingStandardsIgnoreStart
            $function = "
    public function $belongsToManyFunctionName() {".'
        return $this->belongsToMany'."(\\".$this->namespace."\\$belongsToManyModel::class, '$through', '$key1', '$key2');
    }
";
            // @codingStandardsIgnoreEnd
            $functions .= $function;
        }

        return $functions;
    }

    private function getPluralFunctionName($modelName)
    {
        $modelName = lcfirst($modelName);
        return str_plural($modelName);
    }

    private function getSingularFunctionName($modelName)
    {
        $modelName = lcfirst($modelName);
        return str_singular($modelName);
    }

    private function generateModelNameFromTableName($table)
    {
        return ucfirst(camel_case(str_singular($table)));
    }

    private function getColumnsPrimaryAndForeignKeysPerTable($tables)
    {
        $prep = [];
        foreach ($tables as $table) {
            //get foreign keys
            $foreignKeys = $this->schema->getForeignKeyConstraints($table->table_name);
            //get primary keys
            $primaryKeys = $this->schema->listTableIndexes($table->table_name);
            // get columns lists
            $__columns = $this->schema->listTableColumns($table->table_name);

            $columns = [];
            foreach ($__columns as $col) {
                $columns[] = $col->toArray()['name'];
            }

            $prep[$table->table_name] = [
                'foreign' => $foreignKeys,
                'primary' => $primaryKeys,
                'columns' => $columns
            ];
        }

        return $prep;
    }

    private function getEloquentRules($tables, $prep)
    {
        $rules = [];

        //first create empty ruleset for each table
        foreach ($prep as $table => $properties) {
            $rules[$table] = [
                'primaryKey' => [],
                'hasMany' => [],
                'hasOne' => [],
                'belongsTo' => [],
                'belongsToMany' => [],
                'fillable' => [],
                'timestamps' => false,
            ];
        }

        foreach ($prep as $table => $properties) {
            $foreign = $properties['foreign'];
            $primary = $properties['primary'];
            $columns = $properties['columns'];

            $this->setFillableProperties($table, $rules, $columns);

            // get primary key if exists
            $rules[$table]['primaryKey'] = null;
            if (isset($properties['primary']['primary'])) {
                $rules[$table]['primaryKey'] = $properties['primary']['primary']->getColumns();
            }

            $isManyToMany = $this->detectManyToMany($prep, $table);

            if ($isManyToMany === true) {
                $this->addManyToManyRules($tables, $table, $prep, $rules);
            }

            //the below used to be in an ELSE clause but we should be as verbose as possible
            //when we detect a many-to-many table, we still want to set relations on it
            //else
            {
            foreach ($foreign as $fk) {
                $isOneToOne = $this->detectOneToOne($fk, $primary);

                if ($isOneToOne) {
                    $this->addOneToOneRules($tables, $table, $rules, $fk);
                } else {
                    $this->addOneToManyRules($tables, $table, $rules, $fk);
                }
            }
            }
        }

        return $rules;
    }

    private function setFillableProperties($table, &$rules, $columns)
    {
        $fillable = [];
        foreach ($columns as $column_name) {
            if ($column_name !== 'created_at' && $column_name !== 'updated_at') {
                $fillable[] = "'$column_name'";
            } else {
                $rules[$table]['timestamps'] = true;
            }
        }
        // FIXME rebuild with check column type instead check name !!!
        // delete id column from fillable
        // diff types strict
        $rules[$table]['fillable'] = array_diff($fillable, ["'id'"]);
    }

    private function addOneToManyRules($tables, $table, &$rules, $fk)
    {
        //$table belongs to $FK
        //FK hasMany $table

        $fkTable = $fk['on'];
        $field = $fk['field'];
        $references = $fk['references'];
        if (in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasMany'][] = [$table, $field, $references];
        }
        if (in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    private function addOneToOneRules($tables, $table, &$rules, $fk)
    {
        //$table belongsTo $FK
        //$FK hasOne $table

        $fkTable = $fk['on'];
        $field = $fk['field'];
        $references = $fk['references'];
        if (in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasOne'][] = [$table, $field, $references];
        }
        if (in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    private function addManyToManyRules($tables, $table, $prep, &$rules)
    {
        //$FK1 belongsToMany $FK2
        //$FK2 belongsToMany $FK1

        $foreign = $prep[$table]['foreign'];

        $fk1 = $foreign[0];
        $fk1Table = $fk1['on'];
        $fk1Field = $fk1['field'];
        //$fk1References = $fk1['references'];

        $fk2 = $foreign[1];
        $fk2Table = $fk2['on'];
        $fk2Field = $fk2['field'];
        //$fk2References = $fk2['references'];

        //User belongstomany groups user_group, user_id, group_id
        if (in_array($fk1Table, $tables)) {
            $rules[$fk1Table]['belongsToMany'][] = [$fk2Table, $table, $fk1Field, $fk2Field];
        }
        if (in_array($fk2Table, $tables)) {
            $rules[$fk2Table]['belongsToMany'][] = [$fk1Table, $table, $fk2Field, $fk1Field];
        }
    }

    //if FK is also a primary key, and there is only one primary key, we know this will be a one to one relationship
    private function detectOneToOne($fk, $primary)
    {
        if (count($primary) === 1) {
            foreach ($primary as $prim) {
                if ($prim === $fk['field']) {
                    return true;
                }
            }
        }

        return false;
    }

    //does this table have exactly two foreign keys that are also NOT primary,
    //and no tables in the database refer to this table?
    private function detectManyToMany($prep, $table)
    {
        $properties = $prep[$table];
        $foreignKeys = $properties['foreign'];
        $primaryKeys = $properties['primary'];

        //ensure we only have two foreign keys
        if (count($foreignKeys) === 2) {
            //ensure our foreign keys are not also defined as primary keys
            $primaryKeyCountThatAreAlsoForeignKeys = 0;
            foreach ($foreignKeys as $foreign) {
                foreach ($primaryKeys as $primary) {
                    if ($primary === $foreign['name']) {
                        ++$primaryKeyCountThatAreAlsoForeignKeys;
                    }
                }
            }

            if ($primaryKeyCountThatAreAlsoForeignKeys === 1) {
                //one of the keys foreign keys was also a primary key
                //this is not a many to many. (many to many is only possible when both or none of the
                // foreign keys are also primary)
                return false;
            }

            //ensure no other tables refer to this one
            foreach ($prep as $compareTable => $properties) {
                if ($table !== $compareTable) {
                    foreach ($properties['foreign'] as $prop) {
                        if ($prop['on'] === $table) {
                            return false;
                        }
                    }
                }
            }
            //this is a many to many table!
            return true;
        }

        return false;
    }
}

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

namespace RunCli\Command;

use RunCli\CliTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use RunCli\Generators\SchemaGenerator;

use Exception;

class MakeDBCommand extends Command
{
    use CliTrait;

    protected function configure()
    {
        $this
            ->setName('make:db')
            ->setDescription('Create database')
            ->addArgument(
                'schema',
                InputArgument::OPTIONAL,
                'Set new database schema name'
            )
            ->addArgument(
                'charset',
                InputArgument::OPTIONAL,
                'DEFAULT CHARACTER SET  [OPTIONAL]'
            )
            ->addArgument(
                'collation',
                InputArgument::OPTIONAL,
                'collation  OPTIONAL'
            )
            ->setHelp(<<<EOT
php cli make:db [schema] [charset] [collation]

schema - OPTIONAL, schema name from config or exception generated;
charset - OPTIONAL, default value utf8;
collation - OPTIONAL, default value utf8_general_ci;
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//    $this->initDB();

        $this->input = $input;
        $this->output = $output;

        $this->getConfig();// no ret val

        $_driver = $this->cfg['settings']['db']['default'];
        $schemaName = $input->getArgument('schema') ?:
            $this->cfg['settings']['db']['connections'][$_driver]['database'];
        $charset = $input->getArgument('charset');
        $collation = $input->getArgument('collation');


        $schema = new SchemaGenerator(
            $this->cfg['settings']['db']['connections'][$_driver]
            //      $database,
            //      $ignore,
            //      $ignoreFKNames
        );

        if ($schema->createDatabase($schemaName, $charset, $collation)) {
            $this->blockMessage(
                'Success!',
                'Database ' . $schemaName . ' crated!'
            );
        } else {
            throw new Exception('Error create databse ' . $schemaName . ' :(');
        }
    }
}

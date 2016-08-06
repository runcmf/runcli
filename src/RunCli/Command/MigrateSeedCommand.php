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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateSeedCommand extends Command
{
  protected function configure()
    //public function __construct($args)
  {
    $this
      ->setName('make:migrateseed')
      ->setDescription('Migrate and seed the database.')
      ->addArgument(
        'path',
        InputArgument::REQUIRED,
        'What do you want the controller to be called?'
      )
      ->addArgument(
        'methods',
        InputArgument::IS_ARRAY,
        'What methods do you want (separate multiple method with a space)?'
      )
      ->setHelp(<<<EOT

If you don't specify a start and a stop number it will set by default [0,100]
<info>php console.php phpmaster:fibonacci<env></info>
EOT
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    print_r($input);
//    $this->controllerName = $input->getArgument('controllerName');
//    $this->methods = $input->getArgument('methods');
    if (count($this->args) <= 1) {
      $this->help();
    } else {
      switch ($this->args[1]) {
        case 'migrate':
          $this->runMigrations();
          if (!isset($this->args[2]) || $this->args[2] != '--seed')
            break;
        case 'seed':
          $this->runSeed();
          break;
      }
    }


    $output->writeln("<info>Controller ".$this->controllerName." created with ".count($this->methods)." methods</>");
  }
}
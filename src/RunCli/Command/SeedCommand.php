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

class SeedCommand extends Command
{
  use CliTrait;

  protected function configure()
  {
    $this
      ->setName('make:seed')
      ->setDescription('Seed the database tables from given path. f.ex. vendor/runcmf/runbb')
      ->addArgument(
        'path',
        InputArgument::OPTIONAL,
        'Set Path Seeds Module Root Dir without trailing slash. f.ex. vendor/runcmf/runbb'
      )
//      ->addArgument(
//        'methods',
//        InputArgument::IS_ARRAY,
//        'What methods do you want (separate multiple method with a space)?'
//      )
      ->setHelp(<<<EOT
Some Help Here
<info>php cli make:migrate<env></info>
EOT
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->initDB();
    $this->input = $input;
    $this->output = $output;

    $this->setSeedPathPath( $input->getArgument('path') );

    $files = glob($this->getSeedPathPath() . '/*.php');

    foreach ($files as $file) {
      require_once($file);
      $class = $this->mapFileNameToClassName(basename($file));
      $obj = new $class;

      $output->writeln('<question>filling</question> seed <info>' . $class.'</info>');
      $obj->run();
    }

    $this->blockMessage(
      'Success!',
      'All seed loading done from: '.$this->getSeedPathPath()
    );
  }
}
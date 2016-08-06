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

class MigrateCommand extends Command
{
  use CliTrait;

  protected function configure()
  {
    $this->initDB();

    $this
      ->setName('make:migrate')
      ->setDescription('Migrate the database from given path. f.ex. vendor/runcmf/runbb')
      ->addArgument(
        'path',
        InputArgument::REQUIRED,
        'Set Path Migrations Module Root Dir without trailing slash. f.ex. vendor/runcmf/runbb'
      )
      ->addArgument(
        'arg',
        InputArgument::OPTIONAL,
        'arg [up]|down. OPTIONAL'
      )
      ->setHelp(<<<EOT
Some Help Here
<info>php cli make:migrate<env></info>
EOT
);

  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $path = $input->getArgument('path');
    $arg = $input->getArgument('arg');

    $files = glob(DIR . $path . '/var/migrations/*.php');

    foreach ($files as $file) {
      require_once($file);
      $class = $this->mapFileNameToClassName(basename($file));
      $obj = new $class;

      if ($arg === 'down') {
        $output->writeln('<question>'.$arg.'</question> migration <info>' . $class.'</info>');
        $obj->down();
      } else {
        $arg = 'up';
        $output->writeln('<question>'.$arg.'</question> migration <info>' . $class.'</info>');
        $obj->up();
      }
    }

    $output->writeln('');
    $output->writeln('<comment>'.$arg . ' migrations</comment> <info>done</info>');
  }
}
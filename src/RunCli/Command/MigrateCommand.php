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
    $this
      ->setName('make:migrate')
      ->setDescription('Migrate the database from given path. f.ex. vendor/runcmf/runbb')
      ->addArgument(
        'path',
        InputArgument::OPTIONAL,
        'Set Path Migrations Module Root Dir without trailing slash. f.ex. vendor/runcmf/runbb'
      )
      ->addArgument(
        'arg',
        InputArgument::OPTIONAL,
        'arg [up]|down. OPTIONAL'
      )
      ->setHelp(<<<EOT
Hardcoded path prefix 'DIR' - is path to site root and suffix 'var/migrations'.
What in the middle is optional and you can set it or no.
For example command <info>php cli make:migrate</info>
try get migrations from your site_root/var/migrations
Command <info>php cli make:migrate vendor/runcmf/runbb</info>
try get migrations from your site_root/vendor/runcmf/runbb/var/migrations

arg: arg [up]|down. OPTIONAL
php cli make:migrate '' up
php cli make:migrate '' down
php cli make:migrate vendor/runcmf/runbb up
php cli make:migrate vendor/runcmf/runbb down
EOT
);

  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->initDB();
    $this->input = $input;
    $this->output = $output;

    $path = $input->getArgument('path');
    $arg = $input->getArgument('arg');

    $this->setMigrationPath($path);

    $files = glob($this->getMigrationPath() . '/*_table.php');

    if(empty($files)){
      throw new \Exception( 'No migrations found :(' );
    }

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

    $this->blockMessage(
      'Success!',
      strtoupper($arg) . ' migrations done from: '.$this->migrationPath
    );
  }
}
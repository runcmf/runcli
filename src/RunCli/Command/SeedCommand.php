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
      ->setName('seed:fill')
      ->setDescription('Seed the database tables from given path. f.ex. vendor/runcmf/runbb')
      ->addArgument(
        'path',
        InputArgument::OPTIONAL,
        'Set Path Seeds Module Root Dir without trailing slash. f.ex. vendor/runcmf/runbb'
      )
      ->setHelp(<<<EOT
Hardcoded path prefix 'DIR' - is path to site root and suffix 'var/seeds'.
What in the middle is optional and you can set it or no.
For example command <info>php cli seed:fill</info>
try get seeds from your site_root/var/seeds
Command <info>php cli seed:fill vendor/runcmf/runbb</info>
try get seeds from your site_root/vendor/runcmf/runbb/var/seeds

Example
php cli seed:fill
php cli seed:fill vendor/runcmf/runbb
EOT
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->initDB();
    $this->input = $input;
    $this->output = $output;

    $this->setSeedPath( $input->getArgument('path') );

    $files = $this->glob($this->getSeedPath() . '/*_seeds.php');

    if(empty($files)){
      throw new \Exception( 'No seeds found :(' );
    }
    $cnt=0;
    foreach ($files as $file) {
      require_once($file);
      $class = $this->mapFileNameToClassName(basename($file));
      $obj = new $class;
      $cnt++;
      $output->writeln('<question>filling</question> seed <info>' . $class.'</info>');
      $obj->run();
    }

    $this->blockMessage(
      'Success!',
      $cnt . ' seeds loading done from: '.$this->getSeedPath()
    );
  }
}
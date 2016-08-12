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

use Exception;
use PDO;
use PDOException;

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
    $this->input = $input;
    $this->output = $output;

    $this->getConfig();// no ret val

    $schemaName = $input->getArgument('schema') ?: $this->cfg['settings']['db']['database'];
    $charset = $input->getArgument('charset') ?: 'utf8';
    $collation = $input->getArgument('collation') ?: 'utf8_general_ci';

    $result = false;
    $dsn = $q = '';
    switch ($this->cfg['settings']['db']['driver']){
      case 'mysql':
        $dsn = 'mysql:host='.$this->cfg['settings']['db']['host'];
        $q = 'CREATE DATABASE IF NOT EXISTS `'.$schemaName.'` DEFAULT CHARACTER SET `'.$charset.'` COLLATE `'.$collation.'`;';
        break;
//      case 'pgsql'://FIXME ???
//        $dsn = 'pgsql:host='.$this->cfg['settings']['db']['host'];
//        $q = 'CREATE DATABASE IF NOT EXISTS `'.$schemaName.'` DEFAULT CHARACTER SET `'.$charset.'` COLLATE `'.$collation.'`;';
//        break;
//      case 'sqlite':
//        $dsn = 'sqlite:'.$this->cfg['settings']['db']['database'];
//        $s = '';
//        break;
//      case 'sqlsrv':
//        $dsn = 'sqlsrv:host='.$this->cfg['settings']['db']['host'];
//        $q = 'CREATE DATABASE `'.$schemaName.'`';
//        break;
    }

    if($q === ''){
      throw new Exception( 'DB driver not configured ??? Check configuration :(' );
    }

    try {
      $dbh = new PDO($dsn,
        $this->cfg['settings']['db']['username'],
        $this->cfg['settings']['db']['password']
      );
      // set the PDO error mode to exception
      $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $result = $dbh->exec($q);
    }
    catch(PDOException $e)
    {
      echo print_r($dbh->errorInfo(), true) . "\n\n" . $e->getMessage();
    }

    if($result) {
      $this->blockMessage(
        'Success!',
        'Database ' . $schemaName . ' crated!'
      );
    }else{
      throw new Exception( 'Error create databse '.$schemaName.' :(' );
    }
  }
}
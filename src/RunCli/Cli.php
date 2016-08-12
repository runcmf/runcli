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

namespace RunCli;

use Symfony\Component\Console\Application;

use RunCli\Command\MigrateCommand;
use RunCli\Command\MigrationsGeneratorCommand;
use RunCli\Command\SeedCommand;
use RunCli\Command\SeedGeneratorCommand;
use RunCli\Command\MakeDBCommand;
use RunCli\Command\ControllerCommand;
use RunCli\Command\ModelCommand;

class Cli extends Application
{
  private $version = '0.0.4';

  public function __construct($name)
  {
    parent::__construct($name, $this->version);
    //$c = new Container();
    $this->add(new MakeDBCommand());
    $this->add(new MigrateCommand());
    $this->add(new MigrationsGeneratorCommand());
    $this->add(new SeedCommand());
    $this->add(new SeedGeneratorCommand());
//    $this->add(new ModelCommand());
//    $this->add(new ControllerCommand());
  }
}
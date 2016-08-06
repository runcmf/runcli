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

trait CliTrait
{
  protected $cfg = [];
  private $migration_file_name_pattern = '/^\d+_([\w_]+).php$/i';
  private $seed_file_name_pattern = '/^([A-Z][a-z0-9]+).php$/i';

  protected function getConfig()
  {
    $this->cfg = require __DIR__ . '../../../app/Config/Settings.php';
  }
  protected function initDB()
  {
    $this->cfg = require __DIR__ . '../../../app/Config/Settings.php';
    // Register the database connection with Eloquent
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection($this->cfg['settings']['db']);
    $capsule->setAsGlobal();
    $capsule::connection()->enableQueryLog();
    $capsule->bootEloquent();
    // set timezone for timestamps etc
    date_default_timezone_set('UTC');
  }

  /**
   * Turn file names like '12345678901234_create_user_table.php' into class
   * names like 'CreateUserTable'.
   *
   * @param string $fileName File Name
   * @return string
   */
  protected function mapFileNameToClassName($fileName)
  {
    $matches = array();
    if (preg_match($this->migration_file_name_pattern, $fileName, $matches)) {
      $fileName = $matches[1];
    }

    return str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName)));
  }
}
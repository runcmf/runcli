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

use Illuminate\Database\Capsule\Manager as DB;
use Exception;

trait CliTrait
{

  public static $ignore = ['migrations'];
  public static $remote;

  private $migrationPath;
  private $seedPath;

  protected $input;
  protected $output;
  protected $dialog;

  protected $cfg = [];

  private $migration_file_name_pattern = '/^\d{4}\_\d{2}\_\d{2}\_\d{6}\_([\w_]+).php$/i';
  private $seed_file_name_pattern = '/^([A-Z][a-z0-9]+).php$/i';

  protected function getConfig()
  {
    $this->cfg = require __DIR__ . '/../../../../../app/Config/Settings.php';
  }
  protected function initDB()
  {
    $this->cfg = require __DIR__ . '/../../../../../app/Config/Settings.php';
    // Register the database connection with Eloquent
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection($this->cfg['settings']['db']);
    $capsule->setAsGlobal();
    $capsule::connection()->enableQueryLog();
    $capsule->bootEloquent();
    // set timezone for timestamps etc
    date_default_timezone_set('UTC');
  }

  protected function setMigrationPath($path)
  {
    if(empty($path)){
      $this->migrationPath = DIR . 'var/migrations';
    }else {
      $this->migrationPath = DIR . $path . '/var/migrations';
    }
  }
  protected function getMigrationPath()
  {
    return $this->migrationPath;
  }

  protected function setSeedPathPath($path)
  {
    if(empty($path)){
      $this->seedPath = DIR . 'var/seeds';
    }else {
      $this->seedPath = DIR . $path . '/var/seeds';
    }
  }
  protected function getSeedPathPath()
  {
    return $this->seedPath;
  }
  /**
   * Turn file names like '2016_08_08_082016_create_prefix_user_table.php' into class
   * names like 'CreateUserTable'.
   *
   * @param string $fileName File Name
   * @return string
   */
  protected function mapFileNameToClassName($fileName)
  {
    $matches = [];
    if (preg_match($this->migration_file_name_pattern, $fileName, $matches)) {
      $fileName = $matches[1];
    }

    return str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName)));
  }

  protected function getTemplatePath($tpl='')
  {
    if (empty($tpl)) {
      throw new Exception('No template set');
    }

    return __DIR__.'/templates/'.$tpl.'.txt';
  }

  public function compile($template, array $data)
  {
    $template = $this->getTemplatePath($template);
    $template = file_get_contents($template);

    foreach ($data as $key => $value) {
      $template = preg_replace("/\\$$key\\$/i", $value, $template);
    }
    return $template;
  }

  /**
   * Get the database name from the app/config/database.php file
   * @return String
   */
  protected function getDatabaseName()
  {
    return $this->cfg['settings']['db']['database'];
  }

  protected function blockMessage($title, $message, $style = 'info')
  {
    $formatter = $this->getHelperSet()->get('formatter');
    $formattedBlock = $formatter->formatBlock([$title, $message], $style, true);
    $this->output->writeln($formattedBlock);
  }

  protected function sectionMessage($title, $message)
  {
    $formatter = $this->getHelperSet()->get('formatter');
    $formattedLine = $formatter->formatSection($title, $message);
    $this->output->writeln($formattedLine);
  }
}
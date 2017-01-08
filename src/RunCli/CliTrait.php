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

use Exception;
use Illuminate\Database\Capsule\Manager as DB;

trait CliTrait
{
    public static $ignore = ['migrations'];
    public static $remote;

    private $migrationPath;
    protected $migrationName;
    private $seedPath;
    private $modelPath;

    protected $input;
    protected $output;
    protected $dialog;

    protected $dbDefault;

    protected $cfg = [];
    protected $dateTemplate = 'Y_m_d_His';

    private $migration_file_name_pattern = '/^\d{4}\_\d{2}\_\d{2}\_\d{6}\_([\w_]+).php$/i';

    protected function getConfig()
    {
        $root = __DIR__ . '/../../../../../';
        if (file_exists($root . 'app/Config/Settings.php')) {//runcmf/runcmf-skeleton
            $this->cfg = require $root . 'app/Config/Settings.php';
        } elseif (file_exists($root . 'app/settings.php')) {//akrabat/slim3-skeleton
            $this->cfg = require $root . 'app/settings.php';
        } else {// fake config
            $this->cfg = require __DIR__ . '/../../tests/Settings.php';
        }
        defined('DIR') or define('DIR', $root);
    }

    protected function initDB()
    {
        $this->getConfig();
        // Register the database connection with Eloquent
        $this->dbDefault = $this->cfg['settings']['db']['default'];
        $capsule = new \Illuminate\Database\Capsule\Manager;
        $capsule->addConnection($this->cfg['settings']['db']['connections'][$this->dbDefault]);
        $capsule->setAsGlobal();
        //$capsule::connection()->enableQueryLog();
        $capsule->bootEloquent();
        // set timezone for timestamps etc
        //date_default_timezone_set('UTC');
    }

    protected function setModelPath($path)
    {
        if (empty($path)) {
            $this->modelPath = DIR . 'var/models';
        } else {
            $this->modelPath = DIR . $path . '/var/models';
        }
    }

    protected function getModelPath()
    {
        return $this->modelPath;
    }

    protected function setMigrationPath($path)
    {
        if (empty($path)) {
            $this->migrationPath = DIR . 'var/migrations';
        } else {
            $this->migrationPath = DIR . $path . '/var/migrations';
        }
    }

    protected function getMigrationPath()
    {
        return $this->migrationPath;
    }

    protected function setSeedPath($path)
    {
        if (empty($path)) {
            $this->seedPath = DIR . 'var/seeds';
        } else {
            $this->seedPath = DIR . $path . '/var/seeds';
        }
    }

    protected function getSeedPath()
    {
        return $this->seedPath;
    }

    /**
     * Turn file names like '2016_08_08_080808_create_prefix_user_table_suffix.php'
     * into class names like 'CreatePrefixUserTableSuffix'.
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

    protected function getTemplate($tpl = '')
    {
        if (empty($tpl)) {
            throw new Exception('No template set');
        }

        try {
            return __DIR__ . '/templates/' . $tpl . '.stub';
        } catch (Exception $e) {
            throw new Exception('Template ' . $tpl . ' not found :(');
        }

    }

    public function compile($template, array $data)
    {
        $template = $this->getTemplate($template);
        $template = $this->fileGet($template);

        foreach ($data as $key => $value) {
            $template = preg_replace("/\\$$key\\$/i", $value, $template);
        }
        return $template;
    }

    protected function getDatabaseName()
    {
        return DB::connection()->getDatabaseName();
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

    protected function glob($pattern, $flags = 0)
    {
        return glob($pattern, $flags);
    }

    protected function make($file, $content)
    {
        if($this->fileExists($file))
        {
            throw new Exception;
        }
        return $this->fileSave($file, $content);
    }

    protected function fileExists($file)
    {
        return file_exists($file);
    }

    protected function fileSave($path, $content)
    {
        return file_put_contents($path, $content);
    }

    protected function fileGet($file)
    {
        return file_get_contents($file);
    }

    protected function camel($str)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $str))));
    }
}
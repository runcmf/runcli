<?php // @codingStandardsIgnoreStart
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

namespace Tests\RunCli;

use RunCli\CliTrait;
use Illuminate\Database\Capsule\Manager as DB;

class TraitDummy
{
    use CliTrait;

    public function getConfigProtected()
    {
        $this->getConfig();
    }
    public function getCfg()
    {
        return $this->cfg;
    }
    public function getDBConfigProtected()
    {
        $this->getDBConfig();
    }
    public function initDBProtected()
    {
        $this->initDB();
    }
    public function setMigrationPathProtected($path)
    {
        $this->setMigrationPath($path);
    }
    public function getMigrationPathProtected()
    {
        return $this->getMigrationPath();
    }
    public function setSeedPathProtected($path)
    {
        $this->setSeedPath($path);
    }
    public function getSeedPathProtected()
    {
        return $this->getSeedPath();
    }
}

/**
 * @runTestsInSeparateProcesses
 * Class CliTraitTest
 * @package Tests\RunCli
 */
class CliTraitTest extends \PHPUnit_Framework_TestCase
{

    public $path = null;
    private $dummy;

    protected function setUp()
    {
        $this->path = realpath(__DIR__ . '/../../');
        $this->dummy = new TraitDummy();
    }

    /**
     * @covers \RunCli\CliTrait::getConfig()
     */
    public function testGetConfig()
    {
        $this->assertTrue(empty($this->dummy->cfg));
        $this->dummy->getConfigProtected();
        $this->assertArrayHasKey('settings', $this->dummy->getCfg());
    }

    /**
     * @covers \RunCli\CliTrait::initDB()
     */
    public function testInitDB()
    {
        $this->dummy->initDBProtected();
        $schema = DB::schema();
        $this->assertInstanceOf('\Illuminate\Database\Schema\Builder', $schema);
    }

    /**
     * @covers \RunCli\CliTrait::setMigrationPath()
     * @covers \RunCli\CliTrait::getMigrationPath()
     */
    public function testMigrationPath()
    {
        // get config
        $this->dummy->getConfigProtected();
        $this->assertArrayHasKey('settings', $this->dummy->getCfg());

        $this->dummy->setMigrationPathProtected('vendor/dummy');
//        fwrite(STDERR, 'mig path: ' . $this->dummy->getMigrationPathProtected(). "\n");
        $this->assertRegexp('#vendor/dummy#', $this->dummy->getMigrationPathProtected());
        $this->assertNotContains('zzssxx', $this->dummy->getMigrationPathProtected());
    }

    /**
     * @covers \RunCli\CliTrait::setSeedPath()
     * @covers \RunCli\CliTrait::getSeedPath()
     */
    public function testSeedPath()
    {
        // get config
        $this->dummy->getConfigProtected();
        $this->assertArrayHasKey('settings', $this->dummy->getCfg());

        $this->dummy->setSeedPathProtected('vendor/dummy');
        $this->assertRegexp('#vendor/dummy#', $this->dummy->getSeedPathProtected());
        $this->assertNotContains('zzssxx', $this->dummy->getSeedPathProtected());
    }
}
// @codingStandardsIgnoreEnd

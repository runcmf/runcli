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

namespace Tests\RunCli\Adapter;

class IdentifierTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers \RunCli\Adapter\Identifier::__construct()
     * @covers \RunCli\Adapter\AbstractAsset::getName()
     * @covers \RunCli\Adapter\AbstractAsset::isQuoted()
     */
    public function testIdentifierConstructor()
    {
        $i = new \RunCli\Adapter\Identifier('someTable');

        $this->assertInstanceOf('\RunCli\Adapter\AbstractAsset', $i);
        $this->assertEquals('someTable', $i->getName());
        $this->assertFalse($i->isQuoted());

        $i = new \RunCli\Adapter\Identifier('[someTable]');
//        fwrite(STDERR, '->isQuoted(): ' . $i->isQuoted() . " ###\n");
        $this->assertTrue($i->isQuoted());
    }
}
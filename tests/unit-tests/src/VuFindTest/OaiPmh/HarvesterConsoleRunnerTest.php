<?php

/**
 * OAI-PMH harvester console runner unit test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace VuFindTest\Harvest\OaiPmh;

use VuFindHarvest\OaiPmh\HarvesterConsoleRunner;

/**
 * OAI-PMH harvester console runner unit test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class HarvesterConsoleRunnerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock harvester object
     *
     * @return \VuFindHarvest\OaiPmh\Harvester
     */
    protected function getMockHarvester()
    {
        return $this->getMockBuilder('VuFindHarvest\OaiPmh\Harvester')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Test help screen
     *
     * @return void
     */
    public function testHelp()
    {
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $opts->setArguments(['--help']);
        $runner = new HarvesterConsoleRunner($opts);
        ob_start();
        $this->assertTrue($runner->run());
        $this->assertEquals('Usage:', substr(ob_get_contents(), 0, 6));
        ob_end_clean();
    }

    /**
     * Test run with no parameters.
     *
     * @return void
     */
    public function testMissingParameters()
    {
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $opts->setArguments([]);
        $runner = new HarvesterConsoleRunner($opts, null, null, null, true);
        $this->assertFalse($runner->run());
    }

    /**
     * Test run with incomplete parameters.
     *
     * @return void
     */
    public function testIncompleteParameters()
    {
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $opts->setArguments(['foo']);
        $runner = new HarvesterConsoleRunner($opts, null, null, null, true);
        $this->assertFalse($runner->run());
    }

    /**
     * Test run with bad .ini setting.
     *
     * @return void
     */
    public function testInvalidIniSection()
    {
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $opts->setArguments(['--ini=' . $ini, 'badsection']);
        $runner = new HarvesterConsoleRunner($opts, null, null, null, true);
        $this->assertFalse($runner->run());
    }

    /**
     * Test run with bad .ini file.
     *
     * @return void
     */
    public function testInvalidIniFile()
    {
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $ini = realpath(__DIR__ . '/../../../../fixtures/test-doesnotexist.ini');
        $opts->setArguments(['--ini=' . $ini]);
        $runner = new HarvesterConsoleRunner($opts, null, null, null, true);
        $this->assertFalse($runner->run());
    }

    /**
     * Test basic .ini functionality of console runner
     *
     * @return void
     */
    public function testRunFromIniFile()
    {
        $basePath = '/foo/bar';
        $client = $this->getMockBuilder('Zend\Http\Client')->getMock();
        $harvester = $this->getMockHarvester();
        $expectedSettings = [
            'url' => 'http://bar',
            'metadataPrefix' => 'oai_dc',
            'from' => null,
            'until' => null,
            'silent' => false
        ];
        $factory = $this->getMockBuilder('VuFindHarvest\OaiPmh\HarvesterFactory')
            ->getMock();
        $factory->expects($this->once())
            ->method('getHarvester')
            ->with(
                $this->equalTo('foo'), $this->equalTo($basePath),
                $this->equalTo($client), $this->equalTo($expectedSettings)
            )
            ->will($this->returnValue($harvester));
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $opts->setArguments(['--ini=' . $ini]);
        $runner = new HarvesterConsoleRunner(
            $opts, $client, $basePath, $factory, true
        );
        $this->assertTrue($runner->run());
    }

    /**
     * Test basic functionality of console runner w/ settings overridden
     * by command-line options.
     *
     * @return void
     */
    public function testRunFromIniFileWithOptionOverrides()
    {
        $basePath = '/foo/bar';
        $client = $this->getMockBuilder('Zend\Http\Client')->getMock();
        $harvester = $this->getMockHarvester();
        $expectedSettings = [
            'url' => 'http://bar',
            'metadataPrefix' => 'oai_dc',
            'from' => null,
            'until' => null,
            'silent' => false,
            'verbose' => true,
            'timeout' => 45,
        ];
        $factory = $this->getMockBuilder('VuFindHarvest\OaiPmh\HarvesterFactory')
            ->getMock();
        $factory->expects($this->once())
            ->method('getHarvester')
            ->with(
                $this->equalTo('foo'), $this->equalTo($basePath),
                $this->equalTo($client), $this->equalTo($expectedSettings)
            )
            ->will($this->returnValue($harvester));
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $opts->setArguments(['--ini=' . $ini, '-v', '--timeout=45', 'foo']);
        $runner = new HarvesterConsoleRunner(
            $opts, $client, $basePath, $factory, true
        );
        $this->assertTrue($runner->run());
    }
}

<?php

/**
 * OAI-PMH harvester command unit test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use VuFindHarvest\Exception\OaiException;
use VuFindHarvest\OaiPmh\HarvesterCommand;

/**
 * OAI-PMH harvester console runner unit test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class HarvesterCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock harvester object
     *
     * @return \VuFindHarvest\OaiPmh\Harvester
     */
    protected function getMockHarvester()
    {
        return $this->getMockBuilder(\VuFindHarvest\OaiPmh\Harvester::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get command tester
     *
     * @param array            $params  Parameters to pass to tester
     * @param HarvesterCommand $command Command object to test (null to create one)
     *
     * @return CommandTester
     */
    protected function getCommandTester($params = [], $command = null)
    {
        $tester = new CommandTester($command ?? new HarvesterCommand());
        $tester->execute($params);
        return $tester;
    }

    /**
     * Test run with no parameters.
     *
     * @return void
     */
    public function testMissingParameters()
    {
        $commandTester = $this->getCommandTester();
        $this->assertEquals(
            'Please specify an .ini file with the --ini flag or a target directory'
            . " with the first parameter.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test run with incomplete parameters.
     *
     * @return void
     */
    public function testIncompleteParameters()
    {
        $commandTester = $this->getCommandTester(['foo']);
        $this->assertEquals(
            'Please specify an .ini file with the --ini flag or a target directory'
            . " with the first parameter.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test run with bad .ini setting.
     *
     * @return void
     */
    public function testInvalidIniSection()
    {
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $commandTester = $this->getCommandTester(
            ['--ini' => $ini, 'target' => 'badsection']
        );
        $this->assertEquals(
            "badsection not found in $ini.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test run with bad .ini file.
     *
     * @return void
     */
    public function testInvalidIniFile()
    {
        $ini = realpath(__DIR__ . '/../../../../fixtures') . '/test-doesnotexist.ini';
        $commandTester = $this->getCommandTester(['--ini' => $ini]);
        $this->assertEquals(
            "Please add OAI-PMH settings to $ini.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test basic .ini functionality of console runner
     *
     * @return void
     */
    public function testRunFromIniFile()
    {
        $basePath = '/foo/bar';
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)->getMock();
        $harvester = $this->getMockHarvester();
        $expectedSettings = [
            'url' => 'http://bar',
            'metadataPrefix' => 'oai_dc',
            'from' => null,
            'until' => null,
            'silent' => false
        ];
        $factory = $this
            ->getMockBuilder(\VuFindHarvest\OaiPmh\HarvesterFactory::class)
            ->getMock();
        $factory->expects($this->once())
            ->method('getHarvester')
            ->with(
                $this->equalTo('foo'), $this->equalTo($basePath),
                $this->equalTo($client), $this->equalTo($expectedSettings)
            )
            ->will($this->returnValue($harvester));
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $commandTester = $this->getCommandTester(
            ['--ini' => $ini],
            new HarvesterCommand($client, $basePath, $factory)
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test that an unexpected exception causes a bad return code.
     *
     * @return void
     */
    public function testExceptionHandling()
    {
        $basePath = '/foo/bar';
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)->getMock();
        $harvester = $this->getMockHarvester();
        $harvester->expects($this->once())->method('launch')->will(
            $this->throwException(new \Exception('kablooie'))
        );
        $factory = $this
            ->getMockBuilder(\VuFindHarvest\OaiPmh\HarvesterFactory::class)
            ->getMock();
        $factory->expects($this->once())
            ->method('getHarvester')
            ->will($this->returnValue($harvester));
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $commandTester = $this->getCommandTester(
            ['--ini' => $ini],
            new HarvesterCommand($client, $basePath, $factory)
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test that an OAI "noRecordsMatch" exception does not cause a bad return code.
     *
     * @return void
     */
    public function testNoMatchExceptionHandling()
    {
        $basePath = '/foo/bar';
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)->getMock();
        $harvester = $this->getMockHarvester();
        $harvester->expects($this->once())->method('launch')->will(
            $this->throwException(new OaiException('noRecordsMatch', 'empty!'))
        );
        $factory = $this
            ->getMockBuilder(\VuFindHarvest\OaiPmh\HarvesterFactory::class)
            ->getMock();
        $factory->expects($this->once())
            ->method('getHarvester')
            ->will($this->returnValue($harvester));
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $commandTester = $this->getCommandTester(
            ['--ini' => $ini],
            new HarvesterCommand($client, $basePath, $factory)
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
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
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)->getMock();
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
        $factory = $this
            ->getMockBuilder(\VuFindHarvest\OaiPmh\HarvesterFactory::class)
            ->getMock();
        $factory->expects($this->once())
            ->method('getHarvester')
            ->with(
                $this->equalTo('foo'), $this->equalTo($basePath),
                $this->equalTo($client), $this->equalTo($expectedSettings)
            )
            ->will($this->returnValue($harvester));
        $ini = realpath(__DIR__ . '/../../../../fixtures/test.ini');
        $commandTester = $this->getCommandTester(
            [
                '--ini' => $ini,
                '--verbose' => true,
                '--timeout' => 45,
                'target' => 'foo'
            ],
            new VerboseHarvesterCommand($client, $basePath, $factory)
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}

/**
 * Since the Symfony framework adds the 'verbose' option at a higher level than
 * the command, we need to add the option in this fake subclass in order to test
 * verbosity here.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class VerboseHarvesterCommand extends HarvesterCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption(
            'verbose',
            'v',
            InputOption::VALUE_NONE,
            'Verbose mode'
        );
    }
}

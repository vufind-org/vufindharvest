<?php

/**
 * OAI-PMH harvester unit test.
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

use VuFindHarvest\OaiPmh\Harvester;

/**
 * OAI-PMH harvester unit test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class HarvesterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Time zone setting used with setup/tearDown
     *
     * @var string
     */
    protected $oldTz;

    /**
     * Setup function -- standardize timezone for consistent results
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->oldTz = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
    }

    /**
     * Teardown function -- restore previous timezone setting
     *
     * @return void
     */
    public function tearDown(): void
    {
        date_default_timezone_set($this->oldTz);
    }

    /**
     * Get mock communicator object
     *
     * @return \VuFindHarvest\OaiPmh\Communicator
     */
    protected function getMockCommunicator()
    {
        return $this->getMockBuilder(\VuFindHarvest\OaiPmh\Communicator::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock RecordWriter object
     *
     * @return \VuFindHarvest\OaiPmh\RecordWriter
     */
    protected function getMockRecordWriter()
    {
        return $this->getMockBuilder(\VuFindHarvest\OaiPmh\RecordWriter::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock StateManager object
     *
     * @return \VuFindHarvest\OaiPmh\StateManager
     */
    protected function getMockStateManager()
    {
        return $this->getMockBuilder(\VuFindHarvest\OaiPmh\StateManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Return protected or private property.
     *
     * Uses PHP's reflection API in order to modify property accessibility.
     *
     * @param object|string $object   Object or class name
     * @param string        $property Property name
     *
     * @throws \ReflectionException Property does not exist
     *
     * @return mixed
     */
    protected function getProperty($object, $property)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    }

    /**
     * Get harvester
     *
     * @param array  $settings     Settings
     * @param object $communicator Communicator
     * @param object $writer       Writer
     * @param object $stateManager State manager
     *
     * @return Harvester
     */
    protected function getHarvester(
        $settings = [],
        $communicator = null,
        $writer = null,
        $stateManager = null
    ) {
        return new Harvester(
            $communicator ?: $this->getMockCommunicator(),
            $writer ?: $this->getMockRecordWriter(),
            $stateManager ?: $this->getMockStateManager(),
            $settings
        );
    }

    /**
     * Get XML Identify response for testing.
     *
     * @return string
     */
    protected function getFakeIdentifyResponse()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/identify_response.xml');
    }

    /**
     * Get an arbitrary OAI-PMH error
     *
     * @return string
     */
    protected function getArbitraryErrorResponse()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/arbitrary_error_response.xml');
    }

    /**
     * Get a token error
     *
     * @return string
     */
    protected function getTokenErrorResponse()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/token_error_response.xml');
    }

    /**
     * Get XML ListRecords response for testing.
     *
     * @return string
     */
    protected function getFakeResponse()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/list_records_response.xml');
    }

    /**
     * Get XML ListRecords response for testing (with resumption token).
     *
     * @return string
     */
    protected function getFakeResponseWithToken()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/list_records_token_response.xml');
    }

    /**
     * Test that a single ListRecords call with no resumption token triggers
     * a write to the writer and persists a harvest end date that's based on
     * the OAI host response date.
     *
     * @return void
     */
    public function testSimpleListRecords()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings = ['metadataPrefix' => 'oai_dc'];
        $comm->expects($this->exactly(2))->method('request')
            ->withConsecutive(
                ['Identify', []],
                ['ListRecords', $expectedSettings],
            )
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getFakeIdentifyResponse()),
                simplexml_load_string($this->getFakeResponse())
            );
        $writer = $this->getMockRecordWriter();
        $writer->expects($this->once())->method('write')
            ->with($this->isInstanceOf('SimpleXMLElement'))
            ->will($this->returnValue(1468434382));
        $sm = $this->getMockStateManager();
        $sm->expects($this->once())->method('saveDate')
            ->with($this->equalTo('2016-07-12T16:19:54Z'));
        $harvester = $this->getHarvester(
            [],
            $comm,
            $writer,
            $sm
        );
        $harvester->launch();
    }

    /**
     * Test that we can retrieve two pages of results.
     *
     * @return void
     */
    public function testListRecordsWithResumption()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings0 = [
            'metadataPrefix' => 'oai_dc', 'set' => 'xyzzy',
            'from' => '2016-07-01', 'until' => '2016-07-31',
        ];
        $expectedSettings1 = ['resumptionToken' => 'more'];
        $comm->expects($this->exactly(2))->method('request')
            ->withConsecutive(
                ['ListRecords', $expectedSettings0],
                ['ListRecords', $expectedSettings1],
            )
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getFakeResponseWithToken()),
                simplexml_load_string($this->getFakeResponse())
            );
        $writer = $this->getMockRecordWriter();
        $writer->expects($this->exactly(2))->method('write')
            ->with($this->isInstanceOf('SimpleXMLElement'));
        $sm = $this->getMockStateManager();
        $sm->expects($this->once())->method('saveState')
            ->with($this->equalTo('xyzzy'), $this->equalTo('more'), $this->equalTo('2016-07-01'));
        $sm->expects($this->once())->method('clearState');
        $harvester = $this->getHarvester(
            [
                'set' => 'xyzzy', 'dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ',
                'from' => '2016-07-01', 'until' => '2016-07-31',
            ],
            $comm,
            $writer,
            $sm
        );
        $harvester->launch();
    }

    /**
     * Test that harvesting is stopped after x records
     * if stopAfter is set to x.
     *
     * @return void
     */
    public function testListRecordsWithStopAfterOption()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings0 = [
            'metadataPrefix' => 'oai_dc', 'set' => 'xyzzy',
            'from' => '2016-07-01', 'until' => '2016-07-31',
        ];
        $comm->expects($this->exactly(1))->method('request')
            ->withConsecutive(
                ['ListRecords', $expectedSettings0],
            )
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getFakeResponse())
            );
        $writer = $this->getMockRecordWriter();
        $writer->expects($this->exactly(1))->method('write')
            ->with($this->isInstanceOf('SimpleXMLElement'));
        $sm = $this->getMockStateManager();
        $sm->expects($this->once())->method('clearState');
        $sm->expects($this->never())->method('saveState');
        $harvester = $this->getHarvester(
            [
                'set' => 'xyzzy', 'dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ',
                'from' => '2016-07-01', 'until' => '2016-07-31',
                'stopAfter' => 100
            ],
            $comm,
            $writer,
            $sm
        );
        $harvester->launch();
    }

    /**
     * Test a bad resumption token error.
     *
     * @return void
     */
    public function testBadResumptionToken()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expired; removing last_state.txt. Please restart harvest.');

        $comm = $this->getMockCommunicator();
        $expectedSettings = ['resumptionToken' => 'foo'];
        $comm->expects($this->exactly(2))->method('request')
            ->withConsecutive(
                ['Identify', []],
                ['ListRecords', $expectedSettings],
            )
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getFakeIdentifyResponse()),
                simplexml_load_string($this->getTokenErrorResponse())
            );
        $sm = $this->getMockStateManager();
        $sm->expects($this->any())->method('loadState')
            ->will($this->returnValue([null, 'foo', 'bar', 'baz']));
        $sm->expects($this->once())->method('clearState');
        $harvester = $this->getHarvester(
            ['dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ'],
            $comm,
            null,
            $sm
        );
        $harvester->launch();
    }

    /**
     * Test a generic error.
     *
     * @return void
     */
    public function testArbitraryOaiError()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OAI-PMH error -- code: foo, value: bar');

        $comm = $this->getMockCommunicator();
        $expectedSettings = ['metadataPrefix' => 'oai_dc'];
        $comm->expects($this->exactly(2))->method('request')
            ->withConsecutive(
                ['Identify', []],
                ['ListRecords', $expectedSettings],
            )
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getFakeIdentifyResponse()),
                simplexml_load_string($this->getArbitraryErrorResponse())
            );
        $harvester = $this->getHarvester(
            ['dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ'],
            $comm
        );
        $harvester->launch();
    }

    /**
     * With a single ListRecords call test that the persisted harvest end date
     * is formatted appropriately when day granularity is detected.
     *
     * @return void
     */
    public function testSimpleListRecordsGranularityHandling()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings = ['metadataPrefix' => 'oai_dc'];
        $comm->expects($this->exactly(2))->method('request')
            ->withConsecutive(
                ['Identify', []],
                ['ListRecords', $expectedSettings],
            )
            ->willReturnOnConsecutiveCalls(
                simplexml_load_string($this->getFakeIdentifyResponse()),
                simplexml_load_string($this->getFakeResponse())
            );
        $writer = $this->getMockRecordWriter();
        $writer->expects($this->once())->method('write')
            ->with($this->isInstanceOf('SimpleXMLElement'))
            ->will($this->returnValue(1468434382));
        $sm = $this->getMockStateManager();
        $sm->expects($this->once())->method('saveDate')
            ->with($this->equalTo('2016-07-12'));
        $harvester = $this->getHarvester(
            ['dateGranularity' => 'YYYY-MM-DD'],
            $comm,
            $writer,
            $sm
        );
        $harvester->launch();
    }
}

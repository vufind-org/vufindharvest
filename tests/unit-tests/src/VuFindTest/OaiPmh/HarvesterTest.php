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
    public function setUp()
    {
        $this->oldTz = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
    }

    /**
     * Teardown function -- restore previous timezone setting
     *
     * @return void
     */
    public function tearDown()
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
        return $this->getMockBuilder('VuFindHarvest\OaiPmh\Communicator')
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
        return $this->getMockBuilder('VuFindHarvest\OaiPmh\RecordWriter')
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
        return $this->getMockBuilder('VuFindHarvest\OaiPmh\StateManager')
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
    protected function getHarvester($settings = [], $communicator = null,
        $writer = null, $stateManager = null
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
        return <<<XML
<?xml version="1.0"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"><responseDate>2016-07-12T16:19:54Z</responseDate><request verb="Identify">http://fake/OAI/Server</request>
<Identify>
    <repositoryName>fake</repositoryName>
    <baseURL>http://fake/OAI/Server</baseURL>
    <protocolVersion>2.0</protocolVersion>
    <adminEmail>fake@fake.edu</adminEmail>
    <earliestDatestamp>2000-01-01T00:00:00Z</earliestDatestamp>
    <deletedRecord>transient</deletedRecord>
    <granularity>YYYY-MM-DDThh:mm:ssZ</granularity>
    <description>
        <oai-identifier xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">
            <scheme>oai</scheme>
            <repositoryIdentifier>fake</repositoryIdentifier>
            <delimiter>:</delimiter>
            <sampleIdentifier>fake:123456</sampleIdentifier>
        </oai-identifier>
    </description>
</Identify>
</OAI-PMH>
XML;
    }

    /**
     * Get an arbitrary OAI-PMH error
     *
     * @return string
     */
    protected function getArbitraryErrorResponse()
    {
        return <<<XML
<?xml version="1.0"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"><responseDate>2016-07-13T14:11:24Z</responseDate><request verb="ListRecords" resumptionToken="foo">http://fake/OAI/Server</request>
<error code="foo">bar</error>
</OAI-PMH>
XML;
    }

    /**
     * Get a token error
     *
     * @return string
     */
    protected function getTokenErrorResponse()
    {
        return <<<XML
<?xml version="1.0"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"><responseDate>2016-07-13T14:11:24Z</responseDate><request verb="ListRecords" resumptionToken="foo">http://fake/OAI/Server</request>
<error code="badResumptionToken">Invalid or expired resumption token</error>
</OAI-PMH>
XML;
    }

    /**
     * Get XML ListRecords response for testing.
     *
     * @return string
     */
    protected function getFakeResponse()
    {
        return <<<XML
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"><responseDate>2016-07-12T16:22:05Z</responseDate><request verb="ListRecords" metadataPrefix="oai_dc">http://fake/OAI/Server</request>
    <ListRecords>
        <record>
            <header status="deleted">
                <identifier>fake:foo1</identifier>
            </header>
        </record>
        <record>
            <header>
                <identifier>fake:foo2</identifier>
            </header>
            <metadata>
                <foo />
           </metadata>
        </record>
    </ListRecords>
</OAI-PMH>
XML;
    }

    /**
     * Get XML ListRecords response for testing (with resumption token).
     *
     * @return string
     */
    protected function getFakeResponseWithToken()
    {
        return <<<XML
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd"><responseDate>2016-07-12T16:22:05Z</responseDate><request verb="ListRecords" metadataPrefix="oai_dc">http://fake/OAI/Server</request>
    <ListRecords>
        <record>
            <header>
                <identifier>fake:foo0</identifier>
            </header>
            <metadata>
                <foo />
           </metadata>
        </record>
        <resumptionToken>more</resumptionToken>
    </ListRecords>
</OAI-PMH>
XML;
    }

    /**
     * Test that granularity is autoloaded by default.
     *
     * @return void
     */
    public function testGranularityAutoload()
    {
        $comm = $this->getMockCommunicator();
        $comm->expects($this->once(0))->method('request')
            ->with($this->equalTo('Identify'))
            ->will(
                $this->returnValue(
                    simplexml_load_string($this->getFakeIdentifyResponse())
                )
            );
        $harvester = $this->getHarvester([], $comm);
        $this->assertEquals(
            'YYYY-MM-DDThh:mm:ssZ', $this->getProperty($harvester, 'granularity')
        );
    }

    /**
     * Test that a single ListRecords call with no resumption token triggers
     * a write to the writer.
     *
     * @return void
     */
    public function testSimpleListRecords()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings = ['metadataPrefix' => 'oai_dc'];
        $comm->expects($this->once())->method('request')
            ->with($this->equalTo('ListRecords'), $this->equalTo($expectedSettings))
            ->will($this->returnValue(simplexml_load_string($this->getFakeResponse())));
        $writer = $this->getMockRecordWriter();
        $writer->expects($this->once())->method('write')
            ->with($this->isInstanceOf('SimpleXMLElement'))
            ->will($this->returnValue(1468434382));
        $sm = $this->getMockStateManager();
        $sm->expects($this->once())->method('saveDate')
            ->with($this->equalTo('2016-07-13T14:26:22Z'));
        $harvester = $this->getHarvester(
            ['dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ'], $comm, $writer, $sm
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
        $comm->expects($this->at(0))->method('request')
            ->with($this->equalTo('ListRecords'), $this->equalTo($expectedSettings0))
            ->will($this->returnValue(simplexml_load_string($this->getFakeResponseWithToken())));
        $expectedSettings1 = ['resumptionToken' => 'more'];
        $comm->expects($this->at(1))->method('request')
            ->with($this->equalTo('ListRecords'), $this->equalTo($expectedSettings1))
            ->will($this->returnValue(simplexml_load_string($this->getFakeResponse())));
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
            $comm, $writer, $sm
        );
        $harvester->launch();
    }

    /**
     * Test a bad resumption token error.
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Token expired; removing last_state.txt. Please restart harvest.
     */
    public function testBadResumptionToken()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings = ['resumptionToken' => 'foo'];
        $comm->expects($this->once())->method('request')
            ->with($this->equalTo('ListRecords'), $this->equalTo($expectedSettings))
            ->will(
                $this->returnValue(
                    simplexml_load_string($this->getTokenErrorResponse())
                )
            );
        $sm = $this->getMockStateManager();
        $sm->expects($this->any())->method('loadState')
            ->will($this->returnValue([null, 'foo', 'bar']));
        $sm->expects($this->once())->method('clearState');
        $harvester = $this->getHarvester(
            ['dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ'], $comm, null, $sm
        );
        $harvester->launch();
    }

    /**
     * Test a generic error.
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage OAI-PMH error -- code: foo, value: bar
     */
    public function testArbitraryOaiError()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings = ['metadataPrefix' => 'oai_dc'];
        $comm->expects($this->once())->method('request')
            ->with($this->equalTo('ListRecords'), $this->equalTo($expectedSettings))
            ->will(
                $this->returnValue(
                    simplexml_load_string($this->getArbitraryErrorResponse())
                )
            );
        $harvester = $this->getHarvester(
            ['dateGranularity' => 'YYYY-MM-DDThh:mm:ssZ'], $comm
        );
        $harvester->launch();
    }
}

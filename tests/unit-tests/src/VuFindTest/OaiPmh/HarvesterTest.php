<?php

/**
 * OAI-PMH harvester unit test.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
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
class HarvesterTest extends \PHPUnit_Framework_TestCase
{
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
     * Test basic harvest
     *
     * @return void
     */
    public function testHarvest()
    {
        $comm = $this->getMockCommunicator();
        $expectedSettings = ['metadataPrefix' => 'oai_dc'];
        $comm->expects($this->at(0))->method('request')
            ->with($this->equalTo('Identify'))
            ->will(
                $this->returnValue(
                    simplexml_load_string($this->getFakeIdentifyResponse())
                )
            );
        $comm->expects($this->at(1))->method('request')
            ->with($this->equalTo('ListRecords'), $this->equalTo($expectedSettings))
            ->will($this->returnValue(simplexml_load_string($this->getFakeResponse())));
        $harvester = $this->getHarvester([], $comm);
        $harvester->launch();
    }
}

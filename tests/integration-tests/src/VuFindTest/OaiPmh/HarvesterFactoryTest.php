<?php

/**
 * OAI-PMH harvester factory integration test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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

use Laminas\Http\Client;
use VuFindHarvest\OaiPmh\Harvester;
use VuFindHarvest\OaiPmh\HarvesterFactory;

/**
 * OAI-PMH harvester factory integration test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class HarvesterFactoryTest extends \PHPUnit\Framework\TestCase
{
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
     * @param string $target      Name of source being harvested (used as directory
     * name for storing harvested data inside $harvestRoot)
     * @param string $harvestRoot Root directory containing harvested data.
     * @param array  $config      Additional settings
     * @param Client $client      HTTP client
     *
     * @return type
     */
    protected function getHarvester($target, $harvestRoot, $config, $client)
    {
        $factory = new HarvesterFactory();
        return $factory->getHarvester($target, $harvestRoot, $client, $config);
    }

    /**
     * Test configuration.
     *
     * @return void
     */
    public function testConfig()
    {
        $config = [
            'url' => 'http://localhost',
            'set' => 'myset',
            'metadataPrefix' => 'fakemdprefix',
            'dateGranularity' => 'mygranularity',
        ];
        $oai = $this->getHarvester(
            'test',
            sys_get_temp_dir(),
            $config,
            $this->getMockClient()
        );

        // Special cases where config key != class property:
        $this->assertEquals(
            $config['dateGranularity'],
            $this->getProperty($oai, 'granularity')
        );
        unset($config['dateGranularity']);
        unset($config['url']);

        // Generic case for remaining configs:
        foreach ($config as $key => $value) {
            $this->assertEquals($value, $this->getProperty($oai, $key));
        }
    }

    /**
     * Test the injectSetName configuration.
     *
     * @return void
     */
    public function testInjectSetNameConfig()
    {
        $client = $this->getMockClient();
        $response = $client->send();
        $response->expects($this->any())
            ->method('isSuccess')
            ->will($this->returnValue(true));
        $response->expects($this->any())
            ->method('getBody')
            ->will($this->returnValue($this->getListSetsResponse()));
        $config = [
            'url' => 'http://localhost',
            'injectSetName' => 'setnametag',
            'dateGranularity' => 'mygranularity',
            'silent' => true,
        ];
        $oai = $this->getHarvester('test', sys_get_temp_dir(), $config, $client);
        $writer = $this->getProperty($oai, 'writer');
        $formatter = $this->getProperty($writer, 'recordFormatter');
        $this->assertEquals(
            $config['injectSetName'],
            $this->getProperty($formatter, 'injectSetName')
        );
        $this->assertEquals(
            [
                'Audio (Music)' => 'Audio (Music)',
                'Audio (Non-Music)' => 'Audio (Non-Music)'
            ],
            $this->getProperty($formatter, 'setNames')
        );
    }

    /**
     * Test the sslverifypeer configuration.
     *
     * @return void
     */
    public function testSSLVerifyPeer()
    {
        $client = $this->getMockClient();
        $client->expects($this->once())
            ->method('setOptions')
            ->with($this->equalTo(['sslverifypeer' => false, 'timeout' => 60]));
        $config = [
            'url' => 'http://localhost',
            'sslverifypeer' => false,
            'dateGranularity' => 'mygranularity',
        ];
        $this->getHarvester('test', sys_get_temp_dir(), $config, $client);
    }

    /**
     * Test that a missing URL throws an exception.
     *
     * @return void
     */
    public function testMissingURLThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing base URL for test.');

        $oai = $this->getHarvester('test', sys_get_temp_dir(), [], $this->getMockClient());
    }

    // Internal API

    /**
     * Get a sample ListSets response
     *
     * @return string
     */
    protected function getListSetsResponse()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/list_sets_response.xml');
    }

    /**
     * Get a sample Identify response
     *
     * @return string
     */
    protected function getIdentifyResponse()
    {
        return file_get_contents(__DIR__ . '/../../../../fixtures/identify_response.xml');
    }

    /**
     * Get a fake HTTP client
     *
     * @return \Laminas\Http\Client
     */
    protected function getMockClient()
    {
        $query = $this->getMockBuilder(\Laminas\Stdlib\Parameters::class)->getMock();
        $request = $this->getMockBuilder(\Laminas\Http\Request::class)->getMock();
        $request->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue($query));
        $headers = $this->getMockBuilder(\Laminas\Http\Headers::class)->getMock();
        $response = $this->getMockBuilder(\Laminas\Http\Response::class)->getMock();
        $response->expects($this->any())
            ->method('getHeaders')
            ->will($this->returnValue($headers));
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)->getMock();
        $client->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));
        $client->expects($this->any())
            ->method('setMethod')
            ->will($this->returnValue($client));
        $client->expects($this->any())
            ->method('send')
            ->will($this->returnValue($response));
        return $client;
    }
}

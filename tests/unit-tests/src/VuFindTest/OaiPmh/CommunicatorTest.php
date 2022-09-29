<?php

/**
 * OAI-PMH harvester factory integration test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Ryan Jacobs <rjacobs@crl.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace VuFindTest\Harvest\OaiPmh;

use Laminas\Http\Client;
use VuFindHarvest\OaiPmh\Communicator;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindHarvest\ConsoleOutput\ConsoleWriter;

/**
 * OAI-PMH communicator test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class CommunicatorTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Get Communicator
     *
     * @param string                     $uri       Base URI for OAI-PMH server
     * @param Client                     $client    HTTP client
     *
     * @return type
     */
    protected function getCommunicator($uri, $client)
    {
        return new Communicator($uri, $client);
    }

    /**
     * Test a simple communicator request.
     *
     * @return void
     */
    public function testSimpleRequest()
    {
        $client = $this->getMockClient();
        $expectedResponse = $this->getIdentifyResponse();
        $response = $client->send();
        $response->expects($this->once())
            ->method('isSuccess')
            ->will($this->returnValue(true));
        $response->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue($expectedResponse));
        $uri = 'http://localhost';
        $comm = $this->getCommunicator($uri, $client);
        $this->assertEquals(
            $expectedResponse,
            $comm->request('Identify')
        );
    }

    /**
     * Test communicator request w/503 retry.
     *
     * @return void
     */
    public function test503Retry()
    {
        $client = $this->getMockClient();
        $expectedResponse = $this->getIdentifyResponse();
        $response = $client->send();
        $response->expects($this->once())
            ->method('isSuccess')
            ->will($this->returnValue(true));
        $response->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturnOnConsecutiveCalls(503, 503, 200, 200);
        $response->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue($expectedResponse));
        $header = $this->getMockBuilder(\Laminas\Http\Header\RetryAfter::class)
            ->getMock();
        $header->expects($this->once())
            ->method('getDeltaSeconds')
            ->will($this->returnValue(1));
        $headers = $response->getHeaders();
        $headers->expects($this->any())
            ->method('get')
            ->with($this->equalTo('Retry-After'))
            ->will($this->returnValue($header));
        $uri = 'http://localhost';
        $comm = $this->getCommunicator($uri, $client);
        $mockOutput = $this->getMockBuilder(OutputInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $comm->setOutputWriter(new ConsoleWriter($mockOutput));
        $mockOutput->expects($this->once())
            ->method('writeLn')
            ->with($this->equalTo("Received 503 response; waiting 1 seconds..."));
        $this->assertEquals(
            $expectedResponse,
            $comm->request('Identify')
        );
    }

    /**
     * Test communicator HTTP error detection.
     *
     * @return void
     *
     */
    public function testHTTPErrorDetection()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('HTTP Error');

        $client = $this->getMockClient();
        $response = $client->send();
        $response->expects($this->once())
            ->method('isSuccess')
            ->will($this->returnValue(false));
        $uri = 'http://localhost';
        $comm = $this->getCommunicator($uri, $client);
        $comm->request('Identify');
    }

    // Internal API

    /**
     * Get a sample Identify response
     *
     * @return string
     */
    protected function getIdentifyResponse()
    {
        return '<?xml version="1.0"?><mock>Mock Data</mock>';
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

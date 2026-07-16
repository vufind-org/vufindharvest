<?php

/**
 * OAI-PMH harvester factory integration test.
 *
 * PHP version 8
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ryan Jacobs <rjacobs@crl.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFindTest\Harvest\OaiPmh;

use Laminas\Http\Client;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindHarvest\ConsoleOutput\ConsoleWriter;
use VuFindHarvest\OaiPmh\Communicator;

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
     * Get Communicator.
     *
     * @param string $uri    Base URI for OAI-PMH server
     * @param Client $client HTTP client
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
            ->willReturn(true);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($expectedResponse);
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
            ->willReturn(true);
        $response->expects($this->exactly(4))
            ->method('getStatusCode')
            ->willReturnOnConsecutiveCalls(503, 503, 200, 200);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($expectedResponse);
        $header = $this->createMock(\Laminas\Http\Header\RetryAfter::class);
        $header->expects($this->once())
            ->method('getDeltaSeconds')
            ->willReturn(1);
        $headers = $response->getHeaders();
        $headers
            ->method('get')
            ->with('Retry-After')
            ->willReturn($header);
        $uri = 'http://localhost';
        $comm = $this->getCommunicator($uri, $client);
        $mockOutput = $this->createMock(OutputInterface::class);
        $comm->setOutputWriter(new ConsoleWriter($mockOutput));
        $mockOutput->expects($this->once())
            ->method('writeLn')
            ->with('Received 503 response; waiting 1 seconds...');
        $this->assertEquals(
            $expectedResponse,
            $comm->request('Identify')
        );
    }

    /**
     * Test communicator HTTP error detection.
     *
     * @return void
     */
    public function testHTTPErrorDetection()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('HTTP Error');

        $client = $this->getMockClient();
        $response = $client->send();
        $response->expects($this->once())
            ->method('isSuccess')
            ->willReturn(false);
        $uri = 'http://localhost';
        $comm = $this->getCommunicator($uri, $client);
        $comm->request('Identify');
    }

    // Internal API

    /**
     * Get a sample Identify response.
     *
     * @return string
     */
    protected function getIdentifyResponse()
    {
        return '<?xml version="1.0"?><mock>Mock Data</mock>';
    }

    /**
     * Get a fake HTTP client.
     *
     * @return \Laminas\Http\Client
     */
    protected function getMockClient()
    {
        $query = $this->createMock(\Laminas\Stdlib\Parameters::class);
        $request = $this->createMock(\Laminas\Http\Request::class);
        $request
            ->method('getQuery')
            ->willReturn($query);
        $headers = $this->createMock(\Laminas\Http\Headers::class);
        $response = $this->createMock(\Laminas\Http\Response::class);
        $response
            ->method('getHeaders')
            ->willReturn($headers);
        $client = $this->createMock(\Laminas\Http\Client::class);
        $client
            ->method('getRequest')
            ->willReturn($request);
        $client
            ->method('setMethod')
            ->willReturn($client);
        $client
            ->method('send')
            ->willReturn($response);
        return $client;
    }
}

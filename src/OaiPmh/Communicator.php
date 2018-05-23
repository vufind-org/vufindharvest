<?php
/**
 * OAI-PMH Communicator (handles low-level request/response processing).
 *
 * PHP version 7
 *
 * Copyright (c) Demian Katz 2016.
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
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
namespace VuFindHarvest\OaiPmh;

use VuFindHarvest\ConsoleOutput\WriterAwareTrait;
use VuFindHarvest\ResponseProcessor\ResponseProcessorInterface;
use Zend\Http\Client;
use Zend\Uri\Http;

/**
 * OAI-PMH Communicator (handles low-level request/response processing).
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class Communicator
{
    use WriterAwareTrait;

    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * URL to harvest from
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Response processor
     *
     * @var ResponseProcessorInterface
     */
    protected $responseProcessor;

    /**
     * Constructor
     *
     * @param string                     $uri       Base URI for OAI-PMH server
     * @param Client                     $client    HTTP client
     * @param ResponseProcessorInterface $processor Response processor (optional)
     */
    public function __construct($uri, Client $client,
        ResponseProcessorInterface $processor = null
    ) {
        $this->baseUrl = $uri;
        $this->client = $client;
        $this->responseProcessor = $processor;
    }

    /**
     * Perform a single OAI-PMH request.
     *
     * @param string $verb   OAI-PMH verb to execute.
     * @param array  $params GET parameters for ListRecords method.
     *
     * @return string
     */
    protected function sendRequest($verb, $params)
    {
        // Set up the request:
        $this->client->resetParameters(false, false); // keep cookies/auth
        $this->client->setUri($this->baseUrl);

        // Load request parameters:
        $query = $this->client->getRequest()->getQuery();
        $query->set('verb', $verb);
        foreach ($params as $key => $value) {
            $query->set($key, $value);
        }

        // Perform request:
        return $this->client->setMethod('GET')->send();
    }

    /**
     * Make an OAI-PMH request. Throw an exception if there is an error; return
     * an XML string on success.
     *
     * @param string $verb   OAI-PMH verb to execute.
     * @param array  $params GET parameters for ListRecords method.
     *
     * @return string
     */
    protected function getOaiResponse($verb, $params)
    {
        // Debug:
        $this->write(
            "Sending request: verb = {$verb}, params = " . print_r($params, true)
        );

        // Set up retry loop:
        do {
            $result = $this->sendRequest($verb, $params);
            if ($result->getStatusCode() == 503) {
                $delayHeader = $result->getHeaders()->get('Retry-After');
                $delay = is_object($delayHeader)
                    ? $delayHeader->getDeltaSeconds() : 0;
                if ($delay > 0) {
                    $this->writeLine(
                        "Received 503 response; waiting {$delay} seconds..."
                    );
                    sleep($delay);
                }
            } elseif (!$result->isSuccess()) {
                throw new \Exception('HTTP Error ' . $result->getStatusCode());
            }
        } while ($result->getStatusCode() == 503);

        // If we got this far, there was no error -- send back response.
        return $result->getBody();
    }

    /**
     * Make an OAI-PMH request.  Throw an exception if there is an error; return
     * the processed response on success.
     *
     * @param string $verb   OAI-PMH verb to execute.
     * @param array  $params GET parameters for ListRecords method.
     *
     * @return mixed
     */
    public function request($verb, $params = [])
    {
        $xml = $this->getOaiResponse($verb, $params);
        return $this->responseProcessor
            ? $this->responseProcessor->process($xml) : $xml;
    }
}

<?php
/**
 * OAI-PMH Communicator (handles low-level request/response processing).
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
namespace VuFindHarvest;
use Zend\Http\Client;

/**
 * OAI-PMH Communicator (handles low-level request/response processing).
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class OaiCommunicator
{
    use WriterTrait;

    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * Response processor
     *
     * @var ResponseProcessorInterface
     */
    protected $responseProcessor;

    /**
     * URL to harvest from
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * HTTP client's timeout
     *
     * @var int
     */
    protected $timeout = 60;

    /**
     * Username for HTTP basic authentication (false for none)
     *
     * @var string|bool
     */
    protected $httpUser = false;

    /**
     * Password for HTTP basic authentication (false for none)
     *
     * @var string|bool
     */
    protected $httpPass = false;

    /**
     * Constructor
     *
     * @param Client                     $client    HTTP client
     * @param array                      $settings  OAI-PMH settings from oai.ini.
     * @param ResponseProcessorInterface $processor Response processor (optional)
     */
    public function __construct(Client $client, $settings = [],
        ResponseProcessorInterface $processor = null
    ) {
        // Set up base URL:
        if (empty($settings['url'])) {
            throw new \Exception("Missing base URL.");
        }
        $this->baseUrl = $settings['url'];

        // Store silence setting (configure WriterTrait); note that this class
        // only needs to output messages when we are in verbose mode, so we
        // actually need to check two settings: the global silence setting passed
        // in as $silent, and the 'verbose' value in $settings.
        $silent = isset($settings['silent']) ? $settings['silent'] : true;
        if (!isset($settings['verbose']) || !$settings['verbose']) {
            $silent = true;
        }
        $this->isSilent($silent);

        // Store client:
        $this->client = $client;

        // Build response processor:
        $this->responseProcessor = $processor;

        // Disable SSL verification if requested:
        if (isset($settings['sslverifypeer']) && !$settings['sslverifypeer']) {
            $this->client->setOptions(['sslverifypeer' => false]);
        }

        // Settings that may be mapped directly from $settings to class properties:
        $mappableSettings = ['httpUser', 'httpPass', 'timeout'];
        foreach ($mappableSettings as $current) {
            if (isset($settings[$current])) {
                $this->$current = $settings[$current];
            }
        }
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
        $this->client->resetParameters();
        $this->client->setUri($this->baseUrl);
        $this->client->setOptions(['timeout' => $this->timeout]);

        // Set authentication, if necessary:
        if ($this->httpUser && $this->httpPass) {
            $this->client->setAuth($this->httpUser, $this->httpPass);
        }

        // Load request parameters:
        $query = $this->client->getRequest()->getQuery();
        $query->set('verb', $verb);
        foreach ($params as $key => $value) {
            $query->set($key, $value);
        }

        // Perform request and die on error:
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
            } else if (!$result->isSuccess()) {
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

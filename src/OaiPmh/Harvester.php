<?php
/**
 * OAI-PMH Harvest Tool
 *
 * PHP version 7
 *
 * Copyright (c) Demian Katz 2010.
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

/**
 * OAI-PMH Harvest Tool
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class Harvester
{
    use WriterAwareTrait;

    /**
     * Record writer
     *
     * @var RecordWriter
     */
    protected $writer;

    /**
     * Low-level OAI-PMH communicator
     *
     * @var Communicator
     */
    protected $communicator;

    /**
     * State manager
     *
     * @var StateManager
     */
    protected $stateManager;

    /**
     * Target set(s) to harvest (null for all records)
     *
     * @var string|array
     */
    protected $set = null;

    /**
     * Metadata type to harvest
     *
     * @var string
     */
    protected $metadataPrefix = 'oai_dc';

    /**
     * Harvest end date (null for no specific end)
     *
     * @var string
     */
    protected $harvestEndDate;

    /**
     * Harvest start date (null for no specific start)
     *
     * @var string
     */
    protected $startDate = null;

    /**
     * Date granularity ('auto' to autodetect)
     *
     * @var string
     */
    protected $granularity = 'auto';

    /**
     * Constructor.
     *
     * @param Communicator $communicator Low-level API client
     * @param RecordWriter $writer       Record writer
     * @param StateManager $stateManager State manager
     * @param array        $settings     OAI-PMH settings
     */
    public function __construct(Communicator $communicator, RecordWriter $writer,
        StateManager $stateManager, $settings = []
    ) {
        // Don't time out during harvest!!
        set_time_limit(0);

        // Store dependencies
        $this->communicator = $communicator;
        $this->writer = $writer;
        $this->stateManager = $stateManager;

        // Store other settings
        $this->storeDateSettings($settings);
        $this->storeMiscSettings($settings);

        // Autoload granularity if necessary:
        if ($this->granularity == 'auto') {
            $this->loadGranularity();
        }
    }

    /**
     * Set an end date for the harvest (only harvest records BEFORE this date).
     *
     * @param string $date End date (YYYY-MM-DD format).
     *
     * @return void
     */
    public function setEndDate($date)
    {
        $this->harvestEndDate = $date;
    }

    /**
     * Set a start date for the harvest (only harvest records AFTER this date).
     *
     * @param string $date Start date (YYYY-MM-DD format).
     *
     * @return void
     */
    public function setStartDate($date)
    {
        $this->startDate = $date;
    }

    /**
     * Harvest all available documents.
     *
     * @return void
     */
    public function launch()
    {
        // Normalize sets setting to an array:
        $sets = (array)$this->set;
        if (empty($sets)) {
            $sets = [null];
        }

        // Load last state, if applicable (used to recover from server failure).
        if ($state = $this->stateManager->loadState()) {
            $this->write("Found saved state; attempting to resume.\n");
            list($resumeSet, $resumeToken, $this->startDate) = $state;
        }

        // Loop through all of the selected sets:
        foreach ($sets as $set) {
            // If we're resuming and there are multiple sets, find the right one.
            if (isset($resumeToken) && $resumeSet != $set) {
                continue;
            }

            // If we have a token to resume from, pick up there now...
            if (isset($resumeToken)) {
                $token = $resumeToken;
                unset($resumeToken);
            } else {
                // ...otherwise, start harvesting at the requested date:
                $token = $this->getRecordsByDate(
                    $this->startDate, $set, $this->harvestEndDate
                );
            }

            // Keep harvesting as long as a resumption token is provided:
            while ($token !== false) {
                // Save current state in case we need to resume later:
                $this->stateManager->saveState($set, $token, $this->startDate);
                $token = $this->getRecordsByToken($token);
            }
        }

        // If we made it this far, all was successful, so we should clean up
        // the stored state.
        $this->stateManager->clearState();
    }

    /**
     * Make an OAI-PMH request.  Die if there is an error; return a SimpleXML object
     * on success.
     *
     * @param string $verb   OAI-PMH verb to execute.
     * @param array  $params GET parameters for ListRecords method.
     *
     * @return object        SimpleXML-formatted response.
     */
    protected function sendRequest($verb, $params = [])
    {
        $response = $this->communicator->request($verb, $params);
        $this->checkResponseForErrors($response);
        return $response;
    }

    /**
     * Load date granularity from the server.
     *
     * @return void
     */
    protected function loadGranularity()
    {
        $this->write("Autodetecting date granularity... ");
        $response = $this->sendRequest('Identify');
        $this->granularity = (string)$response->Identify->granularity;
        $this->writeLine("found {$this->granularity}.");
    }

    /**
     * Check an OAI-PMH response for errors that need to be handled.
     *
     * @param object $result OAI-PMH response (SimpleXML object)
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function checkResponseForErrors($result)
    {
        // Detect errors and die if one is found:
        if ($result->error) {
            $attribs = $result->error->attributes();

            // If this is a bad resumption token error and we're trying to
            // restore a prior state, we should clean up.
            if ($attribs['code'] == 'badResumptionToken'
                && $this->stateManager->loadState()
            ) {
                $this->stateManager->clearState();
                throw new \Exception(
                    "Token expired; removing last_state.txt. Please restart harvest."
                );
            }
            throw new \Exception(
                "OAI-PMH error -- code: {$attribs['code']}, " .
                "value: {$result->error}"
            );
        }
    }

    /**
     * Harvest records using OAI-PMH.
     *
     * @param array $params GET parameters for ListRecords method.
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecords($params)
    {
        // Make the OAI-PMH request:
        $response = $this->sendRequest('ListRecords', $params);

        // Save the records from the response:
        if ($response->ListRecords->record) {
            $this->writeLine(
                'Processing ' . count($response->ListRecords->record) . " records..."
            );
            $endDate = $this->writer->write($response->ListRecords->record);
        }

        // If we have a resumption token, keep going; otherwise, we're done -- save
        // the end date.
        if (isset($response->ListRecords->resumptionToken)
            && !empty($response->ListRecords->resumptionToken)
        ) {
            return $response->ListRecords->resumptionToken;
        } elseif (isset($endDate) && $endDate > 0) {
            $dateFormat = ($this->granularity == 'YYYY-MM-DD') ?
                'Y-m-d' : 'Y-m-d\TH:i:s\Z';
            $this->stateManager->saveDate(date($dateFormat, $endDate));
        }
        return false;
    }

    /**
     * Harvest records via OAI-PMH using date and set.
     *
     * @param string $from  Harvest start date (null for no specific start).
     * @param string $set   Set to harvest (null for all records).
     * @param string $until Harvest end date (null for no specific end).
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecordsByDate($from = null, $set = null, $until = null)
    {
        $params = ['metadataPrefix' => $this->metadataPrefix];
        if (!empty($from)) {
            $params['from'] = $from;
        }
        if (!empty($set)) {
            $params['set'] = $set;
        }
        if (!empty($until)) {
            $params['until'] = $until;
        }
        return $this->getRecords($params);
    }

    /**
     * Harvest records via OAI-PMH using resumption token.
     *
     * @param string $token Resumption token.
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecordsByToken($token)
    {
        return $this->getRecords(['resumptionToken' => (string)$token]);
    }

    /**
     * Set date range configuration (support method for constructor).
     *
     * @param array $settings Configuration
     *
     * @return void
     */
    protected function storeDateSettings($settings)
    {
        // Set up start/end dates:
        $from = empty($settings['from'])
            ? $this->stateManager->loadDate() : $settings['from'];
        $until = empty($settings['until']) ? null : $settings['until'];
        $this->setStartDate($from);
        $this->setEndDate($until);
    }

    /**
     * Set miscellaneous configuration (support method for constructor).
     *
     * @param array $settings Configuration
     *
     * @return void
     */
    protected function storeMiscSettings($settings)
    {
        if (isset($settings['set'])) {
            $this->set = $settings['set'];
        }
        if (isset($settings['metadataPrefix'])) {
            $this->metadataPrefix = $settings['metadataPrefix'];
        }
        if (isset($settings['dateGranularity'])) {
            $this->granularity = $settings['dateGranularity'];
        }
    }
}

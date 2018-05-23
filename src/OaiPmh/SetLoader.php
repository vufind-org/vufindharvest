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
class SetLoader
{
    use WriterAwareTrait;

    /**
     * Low-level OAI-PMH communicator
     *
     * @var Communicator
     */
    protected $communicator;

    /**
     * Constructor.
     *
     * @param Communicator $communicator Low-level API client
     */
    public function __construct(Communicator $communicator)
    {
        $this->communicator = $communicator;
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
        $result = $this->communicator->request($verb, $params);
        // Detect errors and die if one is found:
        if ($result->error) {
            $attribs = $result->error->attributes();
            throw new \Exception(
                "OAI-PMH error -- code: {$attribs['code']}, " .
                "value: {$result->error}"
            );
        }
        return $result;
    }

    /**
     * Load set list from the server.
     *
     * @return array
     */
    public function getNames()
    {
        $this->write("Loading set list... ");

        // On the first pass through the following loop, we want to get the
        // first page of sets without using a resumption token:
        $params = [];

        $setNames = [];

        // Grab set information until we have it all (at which point we will
        // break out of this otherwise-infinite loop):
        do {
            // Process current page of results:
            $response = $this->sendRequest('ListSets', $params);
            if (isset($response->ListSets->set)) {
                foreach ($response->ListSets->set as $current) {
                    $spec = (string)$current->setSpec;
                    $name = (string)$current->setName;
                    if (!empty($spec)) {
                        $setNames[$spec] = $name;
                    }
                }
            }

            // Is there a resumption token?  If so, continue looping; if not,
            // we're done!
            $params['resumptionToken']
                = !empty($response->ListSets->resumptionToken)
                ? (string)$response->ListSets->resumptionToken : '';
        } while (!empty($params['resumptionToken']));
        $this->writeLine("found " . count($setNames));
        return $setNames;
    }
}

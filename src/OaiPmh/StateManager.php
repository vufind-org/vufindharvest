<?php
/**
 * OAI-PMH State Manager (for persisting harvest state)
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

/**
 * OAI-PMH State Manager (for persisting harvest state)
 *
 * This class actually serves two distinct functions:
 *
 * Long-term state management: remembering/retrieving the end date of the most
 * recent harvest through saveDate()/loadDate().
 *
 * Short-term state management: remembering resumption tokens to allow for
 * continuation of an interrupted harvest through saveState()/loadState().
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class StateManager
{
    /**
     * File for tracking last harvest date
     *
     * @var string
     */
    protected $lastHarvestFile;

    /**
     * File for tracking last harvest state (for continuing interrupted
     * connection).
     *
     * @var string
     */
    protected $lastStateFile;

    /**
     * Constructor.
     *
     * @param string $basePath Directory to contain state files
     */
    public function __construct($basePath)
    {
        // Check if there is a file containing a start date:
        $this->lastHarvestFile = $basePath . 'last_harvest.txt';
        $this->lastStateFile = $basePath . 'last_state.txt';
    }

    /**
     * Clear the state most recently saved to saveState().
     *
     * @return void
     */
    public function clearState()
    {
        if (file_exists($this->lastStateFile)) {
            unlink($this->lastStateFile);
        }
    }

    /**
     * Retrieve the date from the "last harvested" file and use it as our start
     * date if it is available.
     *
     * @return string
     */
    public function loadDate()
    {
        return (file_exists($this->lastHarvestFile))
            ? trim(current(file($this->lastHarvestFile))) : null;
    }

    /**
     * Load the last saved harvest state. Returns an array of
     * [set, resumption token, start date] if found; false otherwise.
     *
     * @return array|bool
     */
    public function loadState()
    {
        return file_exists($this->lastStateFile)
            ? explode("\t", file_get_contents($this->lastStateFile)) : false;
    }

    /**
     * Save a date to the "last harvested" file.
     *
     * @param string $date Date to save.
     *
     * @return void
     */
    public function saveDate($date)
    {
        file_put_contents($this->lastHarvestFile, $date);
    }

    /**
     * Save a harvest state.
     *
     * @param string $set       Set being harvested
     * @param string $token     Current resumption token
     * @param string $startDate Start date of harvest
     *
     * @return void
     */
    public function saveState($set, $token, $startDate)
    {
        file_put_contents($this->lastStateFile, "$set\t$token\t$startDate");
    }
}

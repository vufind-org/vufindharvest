<?php
/**
 * OAI-PMH Record Writer
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

use VuFindHarvest\RecordWriterStrategy\RecordWriterStrategyInterface;

/**
 * OAI-PMH Record Writer
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class RecordWriter
{
    /**
     * Filename for logging harvested IDs (false for none)
     *
     * @var string|bool
     */
    protected $harvestedIdLog = false;

    /**
     * OAI prefix to strip from ID values
     *
     * @var string
     */
    protected $idPrefix = '';

    /**
     * Regular expression searches
     *
     * @var array
     */
    protected $idSearch = [];

    /**
     * Replacements for regular expression matches
     *
     * @var array
     */
    protected $idReplace = [];

    /**
     * XML record formatter
     *
     * @var RecordXmlFormatter
     */
    protected $recordFormatter;

    /**
     * Writer strategy
     *
     * @var RecordWriterStrategyInterface
     */
    protected $strategy;

    /**
     * Constructor
     *
     * @param RecordWriterStrategyInterface $strategy  Writing strategy
     * @param RecordXmlFormatter            $formatter XML record formatter
     * @param array                         $settings  Configuration settings
     */
    public function __construct($strategy, $formatter, $settings = [])
    {
        $this->recordFormatter = $formatter;
        $this->strategy = $strategy;

        // Settings that may be mapped directly from $settings to class properties:
        $mappableSettings = [
            'harvestedIdLog', 'idPrefix', 'idReplace', 'idSearch',
        ];
        foreach ($mappableSettings as $current) {
            if (isset($settings[$current])) {
                $this->$current = $settings[$current];
            }
        }
    }

    /**
     * Extract the ID from a record object (support method for processRecords()).
     *
     * @param object $record SimpleXML record.
     *
     * @return string        The ID value.
     */
    protected function extractID($record)
    {
        // Normalize to string:
        $id = (string)$record->header->identifier;

        // Strip prefix if found:
        if (substr($id, 0, strlen($this->idPrefix)) == $this->idPrefix) {
            $id = substr($id, strlen($this->idPrefix));
        }

        // Apply regular expression matching:
        if (!empty($this->idSearch)) {
            $id = preg_replace($this->idSearch, $this->idReplace, $id);
        }

        // Return final value:
        return $id;
    }

    /**
     * Normalize a date to a Unix timestamp.
     *
     * @param string $date Date (ISO-8601 or YYYY-MM-DD HH:MM:SS)
     *
     * @return integer     Unix timestamp (or false if $date invalid)
     */
    protected function normalizeDate($date)
    {
        // Remove timezone markers -- we don't want PHP to outsmart us by adjusting
        // the time zone!
        $date = str_replace(['T', 'Z'], [' ', ''], $date);

        // Translate to a timestamp:
        return strtotime($date);
    }

    /**
     * Write a log file of harvested IDs (if configured to do so).
     *
     * @param array $harvestedIds Harvested IDs
     *
     * @return void
     * @throws \Exception
     */
    protected function writeHarvestedIdsLog($harvestedIds)
    {
        // Do we have IDs to log and a log filename?  If so, log them:
        if (!empty($this->harvestedIdLog) && !empty($harvestedIds)) {
            $file = fopen($this->getBasePath() . $this->harvestedIdLog, 'a');
            if (!$file) {
                throw new \Exception("Problem opening {$this->harvestedIdLog}.");
            }
            fputs($file, implode(PHP_EOL, $harvestedIds));
            fclose($file);
        }
    }

    /**
     * Get base path for writes.
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->strategy->getBasePath();
    }

    /**
     * Save harvested records to disk and return the end date.
     *
     * @param object $records SimpleXML records.
     *
     * @return int
     */
    public function write($records)
    {
        // Array for tracking successfully harvested IDs:
        $harvestedIds = [];

        // Date of most recent record encountered:
        $endDate = 0;

        $this->strategy->beginWrite();

        // Loop through the records:
        foreach ($records as $record) {
            // Die if the record is missing its header:
            if (empty($record->header)) {
                throw new \Exception('Unexpected missing record header.');
            }

            // Get the ID of the current record:
            $id = $this->extractID($record);

            // Save the current record, either as a deleted or as a regular file:
            $attribs = $record->header->attributes();
            if (strtolower($attribs['status']) == 'deleted') {
                $this->strategy->addDeletedRecord($id);
            } else {
                $recordXML = $this->recordFormatter->format($id, $record);
                $this->strategy->addRecord($id, $recordXML);
                $harvestedIds[] = $id;
            }

            // If the current record's date is newer than the previous end date,
            // remember it for future reference:
            $date = $this->normalizeDate($record->header->datestamp);
            if ($date && $date > $endDate) {
                $endDate = $date;
            }
        }

        $this->strategy->endWrite();

        $this->writeHarvestedIdsLog($harvestedIds);

        return $endDate;
    }
}

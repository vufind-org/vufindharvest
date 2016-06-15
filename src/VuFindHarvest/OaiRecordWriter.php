<?php
/**
 * OAI-PMH Record Writer
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

/**
 * OAI-PMH Record Writer
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class OaiRecordWriter
{
    /**
     * Directory for storing harvested files
     *
     * @var string
     */
    protected $basePath;

    /**
     * Combine harvested records (per OAI chunk size) into one (collection) file?
     *
     * @var bool
     */
    protected $combineRecords = false;

    /**
     * The wrapping XML tag to be used if combinedRecords is set to true
     *
     * @var string
     */
    protected $combineRecordsTag = '<collection>';

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
     * @var OaiRecordXmlFormatter
     */
    protected $recordFormatter;

    /**
     * Constructor
     *
     * @param string                $basePath  Target directory for harvested files
     * @param OaiRecordXmlFormatter $formatter XML record formatter
     * @param array                 $settings  Configuration settings
     */
    public function __construct($basePath, $formatter, $settings = [])
    {
        $this->basePath = $basePath;
        $this->recordFormatter = $formatter;

        // Settings that may be mapped directly from $settings to class properties:
        $mappableSettings = [
            'combineRecords', 'combineRecordsTag', 'harvestedIdLog',
            'idPrefix', 'idReplace', 'idSearch',
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
     * Support method for building combined XML document.
     *
     * @param string $innerXML XML for inside of document.
     *
     * @return string
     */
    protected function getCombinedXML($innerXML)
    {
        // Determine start and end tags from configuration:
        $start = $this->combineRecordsTag;
        $tmp = explode(' ', $start);
        $end = '</' . str_replace(['<', '>'], '', $tmp[0]) . '>';

        // Assemble the document:
        return $start . $innerXML . $end;
    }

    /**
     * Get the filename for a specific record ID.
     *
     * @param string $id  ID of record to save.
     * @param string $ext File extension to use.
     *
     * @return string     Full path + filename.
     */
    protected function getFilename($id, $ext)
    {
        return $this->basePath . time() . '_' .
            preg_replace('/[^\w]/', '_', $id) . '.' . $ext;
    }

    /**
     * Create a tracking file to record the deletion of a record.
     *
     * @param string|array $ids ID(s) of deleted record(s).
     *
     * @return void
     */
    protected function saveDeletedRecords($ids)
    {
        $ids = (array)$ids; // make sure input is array format
        $filename = $this->getFilename($ids[0], 'delete');
        file_put_contents($filename, implode("\n", $ids));
    }

    /**
     * Save a record to disk.
     *
     * @param string $id  Record ID to use for filename generation.
     * @param string $xml XML to save.
     *
     * @return void
     */
    protected function saveFile($id, $xml)
    {
        // Save our XML:
        file_put_contents($this->getFilename($id, 'xml'), trim($xml));
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

        // Array for tracking deleted IDs and string for tracking inner HTML
        // (both of these variables are used only when in 'combineRecords' mode):
        $deletedIds = [];
        $innerXML = '';

        // Loop through the records:
        foreach ($records as $record) {
            // Die if the record is missing its header:
            if (empty($record->header)) {
                throw new \Exception("Unexpected missing record header.");
            }

            // Get the ID of the current record:
            $id = $this->extractID($record);

            // Save the current record, either as a deleted or as a regular file:
            $attribs = $record->header->attributes();
            if (strtolower($attribs['status']) == 'deleted') {
                if ($this->combineRecords) {
                    $deletedIds[] = $id;
                } else {
                    $this->saveDeletedRecords($id);
                }
            } else {
                $recordXML = $this->recordFormatter->format($id, $record);
                if ($this->combineRecords) {
                    $innerXML .= $recordXML;
                } else {
                    $this->saveFile($id, $recordXML);
                }
                $harvestedIds[] = $id;
            }

            // If the current record's date is newer than the previous end date,
            // remember it for future reference:
            $date = $this->normalizeDate($record->header->datestamp);
            if ($date && $date > $endDate) {
                $endDate = $date;
            }
        }

        if ($this->combineRecords) {
            if (!empty($harvestedIds)) {
                $this->saveFile($harvestedIds[0], $this->getCombinedXML($innerXML));
            }

            if (!empty($deletedIds)) {
                $this->saveDeletedRecords($deletedIds);
            }
        }

        // Do we have IDs to log and a log filename?  If so, log them:
        if (!empty($this->harvestedIdLog) && !empty($harvestedIds)) {
            $file = fopen($this->basePath . $this->harvestedIdLog, 'a');
            if (!$file) {
                throw new \Exception("Problem opening {$this->harvestedIdLog}.");
            }
            fputs($file, implode(PHP_EOL, $harvestedIds));
            fclose($file);
        }

        return $endDate;
    }
}

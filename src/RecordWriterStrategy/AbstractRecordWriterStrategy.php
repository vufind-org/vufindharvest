<?php
/**
 * Abstract record writer strategy (shared base for standard vs. combined modes
 * of saving records).
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
namespace VuFindHarvest\RecordWriterStrategy;

/**
 * Abstract record writer strategy (shared base for standard vs. combined modes
 * of saving records).
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
abstract class AbstractRecordWriterStrategy implements RecordWriterStrategyInterface
{
    /**
     * Directory for storing harvested files
     *
     * @var string
     */
    protected $basePath;

    /**
     * Constructor
     *
     * @param string $basePath Target directory for harvested files
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Get base path for writes.
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
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
}

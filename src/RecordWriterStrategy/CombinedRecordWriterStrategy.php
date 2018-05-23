<?php
/**
 * Strategy for writing records to disk as a combined file.
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
 * Strategy for writing records to disk as a combined file.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class CombinedRecordWriterStrategy extends AbstractRecordWriterStrategy
{
    /**
     * The wrapping XML tag to be used if combinedRecords is set to true
     *
     * @var string
     */
    protected $wrappingTag = '<collection>';

    /**
     * Collection of deleted IDs.
     *
     * @var array
     */
    protected $deletedIds = [];

    /**
     * Collection of XML to include inside final output tag.
     *
     * @var array
     */
    protected $innerXML = '';

    /**
     * The ID of the first successfully harvested record.
     *
     * @var string
     */
    protected $firstHarvestedId = false;

    /**
     * Constructor
     *
     * @param string $basePath Target directory for harvested files
     * @param string $tag      Wrapping tag to contain collection (null for default
     * of <collection>)
     */
    public function __construct($basePath, $tag = null)
    {
        parent::__construct($basePath);
        if (null !== $tag) {
            $this->wrappingTag = $tag;
        }
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
        $start = $this->wrappingTag;
        $tmp = explode(' ', $start);
        $end = '</' . str_replace(['<', '>'], '', $tmp[0]) . '>';

        // Assemble the document:
        return $start . $innerXML . $end;
    }

    /**
     * Called before the writing process begins.
     *
     * @return void
     */
    public function beginWrite()
    {
        $this->deletedIds = [];
        $this->innerXML = '';
        $this->firstHarvestedId = false;
    }

    /**
     * Add the ID of a deleted record.
     *
     * @param string $id ID
     *
     * @return void
     */
    public function addDeletedRecord($id)
    {
        $this->deletedIds[] = $id;
    }

    /**
     * Add a non-deleted record.
     *
     * @param string $id     ID
     * @param string $record Record XML
     *
     * @return void
     */
    public function addRecord($id, $record)
    {
        $this->innerXML .= $record;
        if (false === $this->firstHarvestedId) {
            $this->firstHarvestedId = $id;
        }
    }

    /**
     * Close out the writing process.
     *
     * @return void
     */
    public function endWrite()
    {
        if (false !== $this->firstHarvestedId) {
            $this->saveFile(
                $this->firstHarvestedId, $this->getCombinedXML($this->innerXML)
            );
        }

        if (!empty($this->deletedIds)) {
            $this->saveDeletedRecords($this->deletedIds);
        }
    }
}

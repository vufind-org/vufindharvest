<?php
/**
 * Strategy for writing records to disk individually.
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
 * Strategy for writing records to disk individually.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class IndividualRecordWriterStrategy extends AbstractRecordWriterStrategy
{
    /**
     * Called before the writing process begins.
     *
     * @return void
     */
    public function beginWrite()
    {
        // no special action needed.
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
        $this->saveDeletedRecords($id);
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
        $this->saveFile($id, $record);
    }

    /**
     * Close out the writing process.
     *
     * @return void
     */
    public function endWrite()
    {
        // no special action needed.
    }
}

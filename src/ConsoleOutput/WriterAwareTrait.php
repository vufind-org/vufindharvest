<?php
/**
 * Trait for shared output functionality.
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
namespace VuFindHarvest\ConsoleOutput;

/**
 * Trait for shared output functionality.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
trait WriterAwareTrait
{
    /**
     * Writer helper
     *
     * @var WriterInterface
     */
    protected $outputWriter = null;

    /**
     * Set an object to accept console output messages.
     *
     * @param WriterInterface $writer Writer object
     *
     * @return void
     */
    public function setOutputWriter(WriterInterface $writer)
    {
        $this->outputWriter = $writer;
    }

    /**
     * Write a string to the console output writer (if set).
     *
     * @param string $str String to write.
     *
     * @return void
     */
    protected function write($str)
    {
        // Bypass output when silent:
        if ($this->outputWriter) {
            $this->outputWriter->write($str);
        }
    }

    /**
     * Write a string w/newline to the console output writer (if set).
     *
     * @param string $str String to write.
     *
     * @return void
     */
    protected function writeLine($str)
    {
        // Bypass output when silent:
        if ($this->outputWriter) {
            $this->outputWriter->writeLine($str);
        }
    }
}

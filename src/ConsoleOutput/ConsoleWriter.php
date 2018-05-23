<?php
/**
 * Thin wrapper around console output
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

use Zend\Console\Console;

/**
 * Thin wrapper around console output
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class ConsoleWriter implements WriterInterface
{
    /**
     * Output a string to the Console.
     *
     * @param string $str String to write.
     *
     * @return void
     */
    public function write($str)
    {
        Console::write($str);
    }

    /**
     * Output a string w/newline to the Console.
     *
     * @param string $str String to write.
     *
     * @return void
     */
    public function writeLine($str)
    {
        Console::writeLine($str);
    }
}

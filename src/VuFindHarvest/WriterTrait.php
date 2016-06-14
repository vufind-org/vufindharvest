<?php
/**
 * Trait for shared output functionality.
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
use Zend\Console\Console;

/**
 * Trait for shared output functionality.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
trait WriterTrait
{
    /**
     * Should we suppress output?
     *
     * @var bool
     */
    protected $silent = true;

    /**
     * Set/retrieve silence setting.
     *
     * @param bool $silent Should we be silent? Pass null to keep existing setting.
     *
     * @return bool
     */
    protected function isSilent($silent = null)
    {
        if (null !== $this->silent) {
            $this->silent = $silent;
        }
        return $this->silent;
    }

    /**
     * Write a string to the Console.
     *
     * @param string $str String to write.
     *
     * @return void
     */
    protected function write($str)
    {
        // Bypass output when silent:
        if (!$this->isSilent()) {
            Console::write($str);
        }
    }

    /**
     * Write a string w/newline to the Console.
     *
     * @param string $str String to write.
     *
     * @return void
     */
    protected function writeLine($str)
    {
        // Bypass output when silent:
        if (!$this->isSilent()) {
            Console::writeLine($str);
        }
    }
}

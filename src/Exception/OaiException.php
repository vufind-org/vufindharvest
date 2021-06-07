<?php
/**
 * OAI-PMH exception class
 *
 * PHP version 7
 *
 * Copyright (c) Demian Katz 2021.
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
namespace VuFindHarvest\Exception;

use Throwable;

/**
 * OAI-PMH exception class
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class OaiException extends \RuntimeException
{
    /**
     * Error code
     *
     * @var string
     */
    protected $oaiCode;

    /**
     * Error message
     *
     * @var string
     */
    protected $oaiMessage;

    /**
     * Constructor
     *
     * @param string     $oaiCode    OAI-PMH error code
     * @param string     $oaiMessage OAI-PMH error message
     * @param int        $code       Error code
     * @param ?Throwable $previous   Previous exception
     */
    public function __construct(string $oaiCode, string $oaiMessage, int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->oaiCode = $oaiCode;
        $this->oaiMessage = $oaiMessage;
        $message = "OAI-PMH error -- code: $oaiCode, value: $oaiMessage";
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get OAI-PMH error code
     *
     * @return string
     */
    public function getOaiCode(): string
    {
        return $this->oaiCode;
    }

    /**
     * Get OAI-PMH error message
     *
     * @return string
     */
    public function getOaiMessage(): string
    {
        return $this->oaiMessage;
    }
}

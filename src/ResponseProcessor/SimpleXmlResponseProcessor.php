<?php
/**
 * Class for processing API responses into SimpleXML objects.
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
namespace VuFindHarvest\ResponseProcessor;

/**
 * Class for processing API responses into SimpleXML objects.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class SimpleXmlResponseProcessor implements ResponseProcessorInterface
{
    /**
     * Should we sanitize XML?
     *
     * @var bool
     */
    protected $sanitize = false;

    /**
     * Filename for logging bad XML responses (false for none)
     *
     * @var string|bool
     */
    protected $badXmlLog = false;

    /**
     * An array of regex strings used to sanitize XML
     *
     * @var array
     */
    protected $sanitizeRegex = [];

    /**
     * Constructor
     *
     * @param string $basePath Base path to harvest directory.
     * @param array  $settings OAI-PMH settings from oai.ini.
     */
    public function __construct($basePath, $settings = [])
    {
        $this->sanitize = $settings['sanitize'] ?? false;
        $this->badXmlLog = isset($settings['badXMLLog'])
            ? $basePath . $settings['badXMLLog'] : false;
        $this->sanitizeRegex = $settings['sanitizeRegex']
            ?? ['/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u'];
    }

    /**
     * Log a bad XML response.
     *
     * @param string $xml Bad XML
     *
     * @return void
     */
    protected function logBadXML($xml)
    {
        $file = @fopen($this->badXmlLog, 'a');
        if (!$file) {
            throw new \Exception("Problem opening {$this->badXmlLog}.");
        }
        fputs($file, $xml . "\n\n");
        fclose($file);
    }

    /**
     * Sanitize XML.
     *
     * @param string $xml XML to sanitize
     *
     * @return string
     */
    protected function sanitizeXml($xml)
    {
        // Sanitize the XML if requested:
        $newXML = trim(preg_replace($this->sanitizeRegex, ' ', $xml, -1, $count));

        if ($count > 0 && $this->badXmlLog) {
            $this->logBadXML($xml);
        }

        return $newXML;
    }

    /**
     * Collect LibXML errors into a single string.
     *
     * @return string
     */
    protected function collectXmlErrors()
    {
        $callback = function ($e) {
            return trim($e->message);
        };
        return implode('; ', array_map($callback, libxml_get_errors()));
    }

    /**
     * Process an OAI-PMH response into a SimpleXML object. Throw an exception if
     * an error is detected.
     *
     * @param string $xml Raw XML to process
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function process($xml)
    {
        // Sanitize if necessary:
        if ($this->sanitize) {
            $xml = $this->sanitizeXml($xml);
        }

        // Parse the XML (newer versions of LibXML require a special flag for
        // large documents, and responses may be quite large):
        $flags = LIBXML_VERSION >= 20900 ? LIBXML_PARSEHUGE : 0;
        $oldSetting = libxml_use_internal_errors(true);
        $result = simplexml_load_string($xml, null, $flags);
        $errors = $this->collectXmlErrors();
        libxml_use_internal_errors($oldSetting);
        if (!$result) {
            throw new \Exception('Problem loading XML: ' . $errors);
        }

        // If we got this far, we have a valid response:
        return $result;
    }
}

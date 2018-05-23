<?php
/**
 * OAI-PMH XML Record Formatter
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

/**
 * OAI-PMH XML Record Formatter
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class RecordXmlFormatter
{
    /**
     * Search strings for global search-and-replace.
     *
     * @var array
     */
    protected $globalSearch = [];

    /**
     * Replacement strings for global search-and-replace.
     *
     * @var array
     */
    protected $globalReplace = [];

    /**
     * Tag to use for injecting IDs into XML (false for none)
     *
     * @var string|bool
     */
    protected $injectId = false;

    /**
     * Tag to use for injecting setSpecs (false for none)
     *
     * @var string|bool
     */
    protected $injectSetSpec = false;

    /**
     * Tag to use for injecting set names (false for none)
     *
     * @var string|bool
     */
    protected $injectSetName = false;

    /**
     * Tag to use for injecting datestamp (false for none)
     *
     * @var string|bool
     */
    protected $injectDate = false;

    /**
     * List of header elements to copy into body
     *
     * @var array
     */
    protected $injectHeaderElements = [];

    /**
     * Associative array of setSpec => setName
     *
     * @var array
     */
    protected $setNames = [];

    /**
     * Constructor
     *
     * @param array $settings Configuration settings
     */
    public function __construct($settings = [])
    {
        // Settings that may be mapped directly from $settings to class properties:
        $mappableSettings = [
            'globalSearch', 'globalReplace',
            'injectId', 'injectDate', 'injectHeaderElements',
            'injectSetName', 'injectSetSpec',
        ];
        foreach ($mappableSettings as $current) {
            if (isset($settings[$current])) {
                $this->$current = $settings[$current];
            }
        }

        // Where appropriate, normalize elements to array format:
        $this->globalSearch = (array)$this->globalSearch;
        $this->globalReplace = (array)$this->globalReplace;
        $this->injectHeaderElements = (array)$this->injectHeaderElements;
    }

    /**
     * Fix namespaces in the top tag of the XML document to compensate for bugs
     * in the SimpleXML library.
     *
     * @param string $xml  XML document to clean up
     * @param array  $ns   Namespaces to check
     * @param string $attr Attributes extracted from the <metadata> tag
     *
     * @return string
     */
    protected function fixNamespaces($xml, $ns, $attr = '')
    {
        foreach ($ns as $key => $val) {
            if (!empty($key)
                && strstr($xml, $key . ':') && !strstr($xml, 'xmlns:' . $key)
                && !strstr($attr, 'xmlns:' . $key)
            ) {
                $attr .= ' xmlns:' . $key . '="' . $val . '"';
            }
        }
        if (!empty($attr)) {
            $xml = preg_replace('/>/', ' ' . $attr . '>', $xml, 1);
        }
        return $xml;
    }

    /**
     * Format a line of XML.
     *
     * @param string $tag   Tag name
     * @param string $value Content of tag
     *
     * @return string
     */
    protected function createTag($tag, $value)
    {
        return "<{$tag}>" . htmlspecialchars($value) . "</{$tag}>";
    }

    /**
     * Format the ID as an XML tag for inclusion in final record.
     *
     * @param string $id Record ID
     *
     * @return string
     */
    protected function getIdAdditions($id)
    {
        return $this->injectId ? $this->createTag($this->injectId, $id) : '';
    }

    /**
     * Format setSpec header element as XML tags for inclusion in final record.
     *
     * @param object $setSpec Header setSpec element (in SimpleXML format).
     *
     * @return string
     */
    protected function getHeaderSetAdditions($setSpec)
    {
        $insert = '';
        foreach ($setSpec as $current) {
            $set = (string)$current;
            if ($this->injectSetSpec) {
                $insert .= $this->createTag($this->injectSetSpec, $set);
            }
            if ($this->injectSetName) {
                $name = $this->setNames[$set] ?? $set;
                $insert .= $this->createTag($this->injectSetName, $name);
            }
        }
        return $insert;
    }

    /**
     * Format header elements as XML tags for inclusion in final record.
     *
     * @param object $header Header element (in SimpleXML format).
     *
     * @return string
     */
    protected function getHeaderAdditions($header)
    {
        $insert = '';
        if ($this->injectDate) {
            $insert .= $this
                ->createTag($this->injectDate, (string)$header->datestamp);
        }
        if (isset($header->setSpec)
            && ($this->injectSetSpec || $this->injectSetName)
        ) {
            $insert .= $this->getHeaderSetAdditions($header->setSpec);
        }
        if ($this->injectHeaderElements) {
            foreach ($this->injectHeaderElements as $element) {
                if (isset($header->$element)) {
                    $insert .= $header->$element->asXML();
                }
            }
        }
        return $insert;
    }

    /**
     * Extract attributes from the <metadata> tag that need to be inserted
     * into the metadata record contained within the tag.
     *
     * @param string $raw    The full <metadata> XML
     * @param string $record The metadata record with the outer <metadata> tag
     * stripped off.
     *
     * @return string
     */
    protected function extractMetadataAttributes($raw, $record)
    {
        // remove all attributes from extractedNs that appear deeper in xml; this
        // helps prevent fatal errors caused by the same namespace declaration
        // appearing twice in a single tag.
        $extractedNs = [];
        preg_match('/^<metadata([^\>]*)>/', $raw, $extractedNs);
        $attributes = [];
        preg_match_all(
            '/(^| )([^"]*"?[^"]*"|[^\']*\'?[^\']*\')/',
            $extractedNs[1], $attributes
        );
        $extractedAttributes = '';
        foreach ($attributes[0] as $attribute) {
            $attribute = trim($attribute);
            // if $attribute appears in xml, remove it:
            if (!strstr($record, $attribute)) {
                $extractedAttributes = ($extractedAttributes == '') ?
                    $attribute : $extractedAttributes . ' ' . $attribute;
            }
        }
        return $extractedAttributes;
    }

    /**
     * Perform global search and replace.
     *
     * @param string $xml XML to update.
     *
     * @return string
     */
    protected function performGlobalReplace($xml)
    {
        return empty($this->globalSearch)
            ? $xml
            : preg_replace($this->globalSearch, $this->globalReplace, $xml);
    }

    /**
     * Save a record to disk.
     *
     * @param string $id        ID of record to save.
     * @param object $recordObj Record to save (in SimpleXML format).
     *
     * @return string
     */
    public function format($id, $recordObj)
    {
        if (!isset($recordObj->metadata)) {
            throw new \Exception("Unexpected missing record metadata.");
        }

        $raw = trim($recordObj->metadata->asXML());

        // Extract the actual metadata from inside the <metadata></metadata> tags;
        // there is probably a cleaner way to do this, but this simple method avoids
        // the complexity of dealing with namespaces in SimpleXML.
        //
        // We should also apply global search and replace at this time, if
        // applicable.
        $record = $this->performGlobalReplace(
            preg_replace('/(^<metadata[^\>]*>)|(<\/metadata>$)/m', '', $raw)
        );

        // Collect attributes (for proper namespace resolution):
        $metadataAttributes = $this->extractMetadataAttributes($raw, $record);

        // If we are supposed to inject any values, do so now inside the first
        // tag of the file:
        $insert = $this->getIdAdditions($id)
            . $this->getHeaderAdditions($recordObj->header);
        $xml = !empty($insert)
            ? preg_replace('/>/', '>' . $insert, $record, 1) : $record;

        // Build the final record:
        return trim(
            $this->fixNamespaces(
                $xml, $recordObj->getDocNamespaces(), $metadataAttributes
            )
        );
    }

    /**
     * Do we need access to set information?
     *
     * @return bool
     */
    public function needsSetNames()
    {
        return $this->injectSetName;
    }

    /**
     * Inject set name information.
     *
     * @param array $names Associative array of setSpec => setName
     *
     * @return void
     */
    public function setSetNames($names)
    {
        $this->setNames = $names;
    }
}

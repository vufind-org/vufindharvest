<?php

/**
 * OAI-PMH record writer unit test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace VuFindTest\Harvest\OaiPmh;

use VuFindHarvest\OaiPmh\RecordWriter;
use VuFindHarvest\RecordWriterStrategy\RecordWriterStrategyInterface;
use VuFindHarvest\OaiPmh\RecordXmlFormatter;

/**
 * OAI-PMH record writer unit test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class RecordWriterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Return protected or private property.
     *
     * Uses PHP's reflection API in order to modify property accessibility.
     *
     * @param object|string $object   Object or class name
     * @param string        $property Property name
     *
     * @throws \ReflectionException Property does not exist
     *
     * @return mixed
     */
    protected function getProperty($object, $property)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    }

    /**
     * Get mock XML formatter
     *
     * @return RecordXmlFormatter
     */
    protected function getMockFormatter()
    {
        return $this->getMockBuilder('VuFindHarvest\OaiPmh\RecordXmlFormatter')
            ->getMock();
    }

    /**
     * Get mock writer strategy
     *
     * @return RecordWriterStrategyInterface
     */
    protected function getMockStrategy()
    {
        return $this->getMockBuilder(
            'VuFindHarvest\RecordWriterStrategy\RecordWriterStrategyInterface'
        )->getMock();
    }

    /**
     * Get writer to test
     *
     * @param array                         $config   Configuration
     * @param RecordWriterStrategyInterface $strategy Writer strategy
     * @param RecordXmlFormatter $formatter           XML formatter
     *
     * @return RecordWriter
     */
    protected function getWriter(array $config = [],
        RecordWriterStrategyInterface $strategy = null,
        RecordXmlFormatter $formatter = null
    ) {
        if (null === $strategy) {
            $strategy = $this->getMockStrategy();
        }
        if (null === $formatter) {
            $formatter = $this->getMockFormatter();
        }
        return new RecordWriter($strategy, $formatter, $config);
    }

    /**
     * Test configuration.
     *
     * @return void
     */
    public function testConfig()
    {
        $config = [
            'idPrefix' => 'fakeidprefix',
            'idSearch' => 'search',
            'idReplace' => 'replace',
            'harvestedIdLog' => '/my/harvest.log',
        ];
        $writer = $this->getWriter($config);

        // Generic case for remaining configs:
        foreach ($config as $key => $value) {
            $this->assertEquals($value, $this->getProperty($writer, $key));
        }
    }

    /**
     * Test get base path.
     *
     * @return void
     */
    public function testGetBasePath()
    {
        $strategy = $this->getMockStrategy();
        $strategy->expects($this->once())->method('getBasePath')
            ->will($this->returnValue('foo'));
        $writer = $this->getWriter([], $strategy);
        $this->assertEquals('foo', $writer->getBasePath());
    }

    /**
     * Get XML response for testing.
     *
     * @return string
     */
    protected function getFakeResponse()
    {
        return <<<XML
    <ListRecords>
        <record>
            <header status="deleted">
                <identifier>foo1</identifier>
            </header>
        </record>
        <record>
            <header>
                <identifier>foo2</identifier>
            </header>
            <metadata>
                <foo />
           </metadata>
        </record>
    </ListRecords>
XML;
    }

    /**
     * Test processing records.
     *
     * @return void
     */
    public function testProcessing()
    {
        $config = [
            'idPrefix' => 'foo',
            'idSearch' => '/1/',
            'idReplace' => 'one'
        ];
        $records = simplexml_load_string($this->getFakeResponse());
        $strategy = $this->getMockStrategy();
        $strategy->expects($this->once())->method('addDeletedRecord')
            ->with($this->equalTo('one'));
        $strategy->expects($this->once())->method('addRecord')
            ->with($this->equalTo('2'), $this->equalTo('<formatted />'));
        $formatter = $this->getMockFormatter();
        $formatter->expects($this->once())->method('format')
            ->with($this->equalTo('2'), $this->equalTo($records->record[1]))
            ->will($this->returnValue('<formatted />'));
        $writer = $this->getWriter($config, $strategy, $formatter);
        $writer->write($records);
    }
}

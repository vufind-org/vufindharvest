<?php

/**
 * Record writer strategy factory integration test.
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
namespace VuFindTest\Harvest\RecordWriterStrategy;

use VuFindHarvest\RecordWriterStrategy\RecordWriterStrategyFactory;
use VuFindHarvest\RecordWriterStrategy\CombinedRecordWriterStrategy;

/**
 * Record writer strategy factory integration test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class RecordWriterStrategyFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test creating a real file with the combined record writer strategy.
     *
     * @return void
     */
    public function testCombined()
    {
        $factory = new RecordWriterStrategyFactory();
        $basePath = sys_get_temp_dir() . '/';
        $settings = ['combineRecords' => true];
        $strategy = $factory->getStrategy($basePath, $settings);
        $this->assertTrue($strategy instanceof CombinedRecordWriterStrategy);
        $this->assertEquals($basePath, $strategy->getBasePath());
        $strategy->beginWrite();
        $strategy->addDeletedRecord('d1');
        $strategy->addRecord('r1', '<foo1 />');
        $strategy->addDeletedRecord('d2');
        $strategy->addRecord('r2', '<foo2 />');
        $strategy->endWrite();
        $deleteFile = glob($basePath . '*_d1.delete')[0];
        $recordFile = glob($basePath . '*_r1.xml')[0];
        $this->assertEquals("d1\nd2", file_get_contents($deleteFile));
        unlink($deleteFile);
        $this->assertEquals(
            "<collection><foo1 /><foo2 /></collection>",
            file_get_contents($recordFile)
        );
        unlink($recordFile);
    }
}

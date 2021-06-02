<?php

/**
 * IndividualRecordWriterStrategy unit test.
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

/**
 * IndividualRecordWriterStrategy unit test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class IndividualRecordWriterStrategyTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test strategy
     *
     * @return void
     */
    public function testStrategy()
    {
        $mock = $this->getMockBuilder(
            \VuFindHarvest\RecordWriterStrategy\IndividualRecordWriterStrategy::class
        )->onlyMethods(['saveDeletedRecords', 'saveFile'])
            ->setConstructorArgs(['foo'])->getMock();
        $mock->expects($this->exactly(2))->method('saveDeletedRecords')
            ->withConsecutive(['d1'], ['d2']);
        $mock->expects($this->exactly(2))->method('saveFile')
            ->withConsecutive(['r1'], ['r2']);
        $mock->beginWrite();
        $mock->addDeletedRecord('d1');
        $mock->addRecord('r1', '<foo1 />');
        $mock->addDeletedRecord('d2');
        $mock->addRecord('r2', '<foo2 />');
        $mock->endWrite();
    }
}

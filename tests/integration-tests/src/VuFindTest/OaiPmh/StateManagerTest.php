<?php

/**
 * OAI-PMH state manager integration test.
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

use VuFindHarvest\OaiPmh\StateManager;

/**
 * OAI-PMH state manager integration test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class StateManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test date functionality.
     *
     * @return void
     */
    public function testDate()
    {
        $tmp = sys_get_temp_dir() . '/';
        $manager = new StateManager($tmp);
        $date = '2016-07-12';
        $manager->saveDate($date);
        $this->assertEquals($date, $manager->loadDate());
        unlink($tmp . 'last_harvest.txt');
    }

    /**
     * Test state functionality.
     *
     * @return void
     */
    public function testState()
    {
        $tmp = sys_get_temp_dir() . '/';
        $date = '2016-07-12';
        $manager = new StateManager($tmp);
        $manager->saveState('foo', 'bar', $date);
        $this->assertEquals(['foo', 'bar', $date], $manager->loadState());
        $this->assertTrue(file_exists($tmp . 'last_state.txt'));
        $manager->clearState();
        $this->assertFalse(file_exists($tmp . 'last_state.txt'));
    }
}

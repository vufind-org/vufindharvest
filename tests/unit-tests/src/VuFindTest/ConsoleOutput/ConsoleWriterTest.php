<?php

/**
 * Console writer test
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
namespace VuFindTest\Harvest\ConsoleOutput;

use Symfony\Component\Console\Output\OutputInterface;
use VuFindHarvest\ConsoleOutput\ConsoleWriter;
use VuFindHarvest\ConsoleOutput\WriterAwareTrait;

/**
 * Console writer test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class ConsoleWriterTest extends \PHPUnit\Framework\TestCase
{
    use WriterAwareTrait;

    /**
     * Test console writer
     *
     * @return void
     */
    public function testWriter()
    {
        $mockOutput = $this->getMockBuilder(OutputInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockOutput->expects($this->once())->method('write')
            ->with($this->equalTo('writeTest'));
        $mockOutput->expects($this->once())->method('writeln')
            ->with($this->equalTo('writelnTest'));
        $this->setOutputWriter(new ConsoleWriter($mockOutput));
        $this->write('writeTest');
        $this->writeLine('writelnTest');
    }
}

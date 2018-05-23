<?php

/**
 * SimpleXML response processor unit test.
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

use VuFindHarvest\ResponseProcessor\SimpleXmlResponseProcessor;

/**
 * SimpleXML response processor unit test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class SimpleXmlResponseProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test behavior related to bad XML (without sanitization).
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Problem loading XML: Char 0x0 out of allowed range
     */
    public function testBadXmlWithoutSanitization()
    {
        $badXml = '<illegal value="' . chr(0) . '" />';
        $noSanitize = new SimpleXmlResponseProcessor('/foo/bar');
        $noSanitize->process($badXml);
    }

    /**
     * Test behavior related to bad XML (with sanitization).
     *
     * @return void
     */
    public function testBadXmlWithSanitization()
    {
        $badXml = '<illegal value="' . chr(0) . '" />';
        $basePath = sys_get_temp_dir() . '/';
        $log = 'badxmltest.log';
        $options = ['sanitize' => true, 'badXMLLog' => $log];
        $sanitize = new SimpleXmlResponseProcessor($basePath, $options);
        $result = $sanitize->process($badXml);
        $this->assertEquals(
            '<?xml version="1.0"?>' . "\n" . '<illegal value=" "/>' . "\n",
            $result->asXml()
        );
        $this->assertEquals($badXml . "\n\n", file_get_contents($basePath . $log));
        unlink($basePath . $log);
    }

    /**
     * Test behavior related to bad XML (with sanitization).
     *
     * @return void
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Problem opening /definitely/does/not/exist/foo/bar/xyzzy/foo.log
     */
    public function testBadXmlWithSanitizationAndBadLogDir()
    {
        $badXml = '<illegal value="' . chr(0) . '" />';
        $basePath = '/definitely/does/not/exist/foo/bar/xyzzy/';
        $options = ['sanitize' => true, 'badXMLLog' => 'foo.log'];
        $sanitize = new SimpleXmlResponseProcessor($basePath, $options);
        $sanitize->process($badXml);
    }
}

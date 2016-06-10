<?php
/**
 * OAI-PMH Harvest Tool (Console Wrapper)
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
use Zend\Console\Console, Zend\Console\Getopt, Zend\Http\Client;

/**
 * OAI Class
 *
 * OAI-PMH Harvest Tool (Console Wrapper)
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class OaiHarvesterConsoleRunner
{
    /**
     * Console options
     *
     * @var Getopt
     */
    protected $opts;

    /**
     * Constructor
     *
     * @param Getopt $opts CLI options (omit for defaults)
     */
    public function __construct($opts = null)
    {
        $this->opts = $opts ?: new Getopt([]);
        $this->opts->addRules(
            [
                'from-s' => 'Harvest start date',
                'until-s' => 'Harvest end date',
                'ini-s' => '.ini file to load',
            ]
        );
    }

    /**
     * Run the task and return true on success.
     *
     * @return bool
     */
    public function run()
    {
        if (!$allSettings = $this->getSettings()) {
            return false;
        }

        // Loop through all the settings and perform harvests:
        $processed = 0;
        foreach ($allSettings as $target => $settings) {
            if (empty($target) || empty($settings)) {
                continue;
            }
            Console::writeLine("Processing {$target}...");
            try {
                $this->harvestSingleRepository($target, $settings);
            } catch (\Exception $e) {
                Console::writeLine($e->getMessage());
                return false;
            }
            $processed++;
        }

        // All done.
        Console::writeLine(
            "Completed without errors -- {$processed} source(s) processed."
        );
        return true;
    }

    /**
     * Get the target directory for writing harvested files.
     *
     * @return string
     */
    protected function getHarvestRoot()
    {
        return getcwd();
    }

    /**
     * Get an HTTP client.
     *
     * @return Client
     */
    protected function getHttpClient()
    {
        return new Client();
    }

    /**
     * Load the harvest settings. Return false on error.
     *
     * @return array|bool
     */
    protected function getSettings()
    {
        if (!($ini = $this->opts->getOption('ini'))) {
            Console::writeLine('Please specify an .ini file with the --ini flag.');
            return false;
        }
        $oaiSettings = @parse_ini_file($ini, true);
        if (empty($oaiSettings)) {
            Console::writeLine("Please add OAI-PMH settings to {$ini}.");
            return false;
        }
        $argv = $this->opts->getRemainingArgs();
        if ($section = isset($argv[0]) ? $argv[0] : false) {
            if (!isset($oaiSettings[$section])) {
                Console::writeLine("$section not found in $ini.");
                return false;
            }
            $oaiSettings = [$section => $oaiSettings[$section]];
        }
        return $oaiSettings;
    }

    /**
     * Harvest a single repository.
     *
     * @param string $target  Name of repo (used for target directory)
     * @param array $settings Settings for the harvester.
     *
     * @return void
     * @throws \Exception
     */
    protected function harvestSingleRepository($target, $settings)
    {
        $harvest = new OaiHarvester(
            $target,
            $this->getHarvestRoot(),
            $settings,
            $this->getHttpClient(),
            $this->opts->getOption('from'),
            $this->opts->getOption('until'),
            false   // do not silence output
        );
        $harvest->launch();
    }
}

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
namespace VuFindHarvest\OaiPmh;
use VuFindHarvest\WriterTrait;
use Zend\Console\Getopt;
use Zend\Http\Client;

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
class HarvesterConsoleRunner
{
    use WriterTrait;

    /**
     * Console options
     *
     * @var Getopt
     */
    protected $opts;

    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * Root directory for harvesting
     *
     * @var string
     */
    protected $harvestRoot;

    /**
     * Harvester factory
     *
     * @var HarvesterFactory
     */
    protected $factory;

    /**
     * Constructor
     *
     * @param Getopt           $opts        CLI options (omit for defaults)
     * @param Client           $client      HTTP client (omit for default)
     * @param string           $harvestRoot Root directory for harvesting (omit for
     * default)
     * @param HarvesterFactory $factory     Harvester factory (omit for default)
     * @param bool             $silent      Should we suppress output?
     */
    public function __construct($opts = null, $client = null, $harvestRoot = null,
        HarvesterFactory $factory = null, $silent = false
    ) {
        $this->opts = $opts ?: static::getDefaultOptions();
        $this->client = $client ?: new Client();
        $this->harvestRoot = $harvestRoot ?: getcwd();
        $this->factory = $factory ?: new HarvesterFactory();
        $this->isSilent($silent);
    }

    /**
     * Get the default Options object.
     *
     * @return Getopt
     */
    public static function getDefaultOptions()
    {
        return new Getopt(
            [
                'from-s' => 'Harvest start date',
                'until-s' => 'Harvest end date',
                'ini-s' => '.ini file to load',
                'url-s' => 'Base URL of OAI-PMH server',
                'set-s' => 'Set name to harvest',
                'metadataPrefix-s' => 'Metadata prefix to harvest',
                'timeout-i' => 'HTTP timeout (in seconds)',
            ]
        );
    }

    /**
     * Use command-line switches to add/override settings found in the .ini
     * file, if necessary.
     *
     * @param array $settings Incoming settings
     *
     * @return array
     */
    protected function updateSettingsWithConsoleOptions($settings)
    {
        $directMapSettings = ['url', 'set', 'metadataPrefix', 'timeout'];
        foreach ($directMapSettings as $setting) {
            if ($value = $this->opts->getOption($setting)) {
                $settings[$setting] = $value;
            }
        }
        return $settings;
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
        $processed = $skipped = 0;
        foreach ($allSettings as $target => $baseSettings) {
            $settings = $this->updateSettingsWithConsoleOptions($baseSettings);
            if (empty($target) || empty($settings)) {
                $skipped++;
                continue;
            }
            $this->writeLine("Processing {$target}...");
            try {
                $this->harvestSingleRepository($target, $settings);
            } catch (\Exception $e) {
                $this->writeLine($e->getMessage());
                return false;
            }
            $processed++;
        }

        // All done.
        if ($processed == 0 && $skipped > 0) {
            $this->writeLine(
                'No valid settings found; '
                . 'please set url and metadataPrefix at minimum.'
            );
            return false;
        }
        $this->writeLine(
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
        return $this->harvestRoot;
    }

    /**
     * Get an HTTP client.
     *
     * @return Client
     */
    protected function getHttpClient()
    {
        return $this->client;
    }

    /**
     * Load configuration from an .ini file (or return false on error)
     *
     * @param string      $ini     Configuration file to load
     * @param string|bool $section Section of .ini to load (or false for all)
     *
     * @return array|bool
     */
    protected function getSettingsFromIni($ini, $section)
    {
        $oaiSettings = @parse_ini_file($ini, true);
        if (empty($oaiSettings)) {
            $this->writeLine("Please add OAI-PMH settings to {$ini}.");
            return false;
        }
        if ($section) {
            if (!isset($oaiSettings[$section])) {
                $this->writeLine("$section not found in $ini.");
                return false;
            }
            $oaiSettings = [$section => $oaiSettings[$section]];
        }
        return $oaiSettings;
    }

    /**
     * Load the harvest settings. Return false on error.
     *
     * @return array|bool
     */
    protected function getSettings()
    {
        $ini = $this->opts->getOption('ini');
        $argv = $this->opts->getRemainingArgs();
        $section = isset($argv[0]) ? $argv[0] : false;
        if (!$ini && !$section) {
            $this->writeLine(
                'Please specify an .ini file with the --ini flag'
                . ' or a target directory with the first parameter.'
            );
            return false;
        }
        return $ini
            ? $this->getSettingsFromIni($ini, $section)
            : [$section => []];
    }

    /**
     * Harvest a single repository.
     *
     * @param string $target   Name of repo (used for target directory)
     * @param array  $settings Settings for the harvester.
     *
     * @return void
     * @throws \Exception
     */
    protected function harvestSingleRepository($target, $settings)
    {
        $settings['from'] = $this->opts->getOption('from');
        $settings['until'] = $this->opts->getOption('until');
        $settings['silent'] = false;
        $harvest = $this->factory->getHarvester(
            $target,
            $this->getHarvestRoot(),
            $this->getHttpClient(),
            $settings
        );
        $harvest->launch();
    }
}

<?php
/**
 * OAI-PMH Harvest Tool (Console Wrapper)
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

use VuFindHarvest\ConsoleOutput\ConsoleWriter;
use VuFindHarvest\ConsoleOutput\WriterAwareTrait;
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
    use WriterAwareTrait;

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
        if (!$silent) {
            $this->setOutputWriter(new ConsoleWriter());
        }
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
                'help|h' => 'Display usage message',
                'verbose|v' => 'Display verbose output',
                'from-s' => 'Harvest start date',
                'until-s' => 'Harvest end date',
                'ini-s' => '.ini file to load',
                'url-s' => 'Base URL of OAI-PMH server',
                'httpUser-s' => 'Username to access url (optional)',
                'httpPass-s' => 'Password to access url (optional)',
                'set-s' => 'Set name to harvest',
                'metadataPrefix-s' => 'Metadata prefix to harvest',
                'timeout-i' => 'HTTP timeout (in seconds)',
                'combineRecords' => 'Turn off "one record per file" mode',
                'combineRecordsTag-s' => 'Specify the XML tag wrapped around'
                    . ' multiple records in combineRecords mode'
                    . ' (default = <collection>)',
                'globalSearch-s' => 'Regular expression to replace in raw XML',
                'globalReplace-s' => 'String to replace globalSearch regex matches',
                'injectDate-s' => 'Inject date from header into specified tag',
                'injectId-s' => 'Inject ID from header into specified tag',
                'injectSetName-s' => 'Inject setName from header into specified tag',
                'injectSetSpec-s' => 'Inject setSpec from header into specified tag',
                'idSearch-s' => 'Regular expression to replace in ID'
                    . ' (only relevant when injectId is on)',
                'idReplace-s' => 'String to replace idSearch regex matches',
                'dateGranularity-s' => '"YYYY-MM-DDThh:mm:ssZ," "YYYY-MM-DD" or '
                    . '"auto" (default)',
                'harvestedIdLog-s' => 'Filename (relative to harvest directory)'
                    . ' to store log of harvested IDs.',
                'autosslca' => 'Attempt to autodetect SSL certificate file/path',
                'sslcapath-s' => 'Path to SSL certificate authority directory',
                'sslcafile-s' => 'Path to SSL certificate authority file',
                'nosslverifypeer' => 'Disable SSL verification',
                'sanitize' => 'Strip illegal characters from XML',
                'sanitizeRegex-s' =>
                    'Optional regular expression defining XML characters to remove',
                'badXMLLog-s' => 'Filename (relative to harvest directory) to log'
                    . ' XML fixed by sanitize setting'

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
        $directMapSettings = [
            'url', 'set', 'metadataPrefix', 'timeout', 'combineRecordsTag',
            'injectDate', 'injectId', 'injectSetName', 'injectSetSpec',
            'idSearch', 'idReplace', 'dateGranularity', 'harvestedIdLog',
            'badXMLLog', 'httpUser', 'httpPass', 'sslcapath', 'sslcafile',
            'sanitizeRegex',
        ];
        foreach ($directMapSettings as $setting) {
            if ($value = $this->opts->getOption($setting)) {
                $settings[$setting] = $value;
            }
        }
        $flagSettings = [
            'combineRecords' => ['combineRecords', true],
            'v' => ['verbose', true],
            'autosslca' => ['autosslca', true],
            'nosslverifypeer' => ['sslverifypeer', false],
            'sanitize' => ['sanitize', true],
        ];
        foreach ($flagSettings as $in => $details) {
            if ($this->opts->getOption($in)) {
                list($out, $val) = $details;
                $settings[$out] = $val;
            }
        }
        return $settings;
    }

    /**
     * Render help message.
     *
     * @return void
     */
    public function getHelp()
    {
        $msg = $this->opts->getUsageMessage();
        // Amend the auto-generated help message:
        $options = "[ options ] [ target ]\n"
            . "Where [ target ] is the name of a section of the configuration\n"
            . "specified by the ini option, or a directory to harvest into if\n"
            . "no .ini file is used. If [ target ] is omitted, all .ini sections\n"
            . "will be processed. [ options ] may be selected from those below,\n"
            . "and will override .ini settings where applicable.";
        $this->write(str_replace('[ options ]', $options, $msg));
    }

    /**
     * Run the task and return true on success.
     *
     * @return bool
     */
    public function run()
    {
        // Support help message:
        if ($this->opts->getOption('h')) {
            $this->getHelp();
            return true;
        }

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
        $section = $argv[0] ?? false;
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

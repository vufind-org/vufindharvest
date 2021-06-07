<?php
/**
 * OAI-PMH Harvest Tool (Symfony Console Command)
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

use Laminas\Http\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindHarvest\ConsoleOutput\ConsoleWriter;
use VuFindHarvest\ConsoleOutput\WriterAwareTrait;
use VuFindHarvest\Exception\OaiException;

/**
 * OAI-PMH Harvest Tool (Symfony Console Command)
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 */
class HarvesterCommand extends Command
{
    use WriterAwareTrait;

    /**
     * The name of the command
     *
     * @var string
     */
    protected static $defaultName = 'harvest/harvest_oai';

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
     * Silent mode
     *
     * @var bool
     */
    protected $silent;

    /**
     * Constructor
     *
     * @param Client           $client      HTTP client (omit for default)
     * @param string           $harvestRoot Root directory for harvesting (omit for
     * default)
     * @param HarvesterFactory $factory     Harvester factory (omit for default)
     * @param bool             $silent      Should we suppress output?
     * @param string|null      $name        The name of the command; passing null
     * means it must be set in configure()
     */
    public function __construct($client = null, $harvestRoot = null,
        HarvesterFactory $factory = null, $silent = false, $name = null
    ) {
        $this->client = $client ?: new Client();
        $this->harvestRoot = $harvestRoot ?: getcwd();
        $this->factory = $factory ?: new HarvesterFactory();
        $this->silent = $silent;
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function configure()
    {
        $this
            ->setDescription('OAI-PMH harvester')
            ->setHelp('Harvests metadata using the OAI-PMH protocol.')
            ->addArgument(
                'target',
                InputArgument::OPTIONAL,
                'the name of a section of the configuration specified by the ini '
                . "option,\nor a directory to harvest into if no .ini file is used. "
                . "If <target> is\nomitted, all .ini sections will be processed."
            )->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                'Harvest start date'
            )->addOption(
                'until',
                null,
                InputOption::VALUE_REQUIRED,
                'Harvest end date'
            )->addOption(
                'ini',
                null,
                InputOption::VALUE_REQUIRED,
                '.ini file to load; if you set other more specific options, they'
                . " will\noverride equivalent settings loaded from the .ini file."
            )->addOption(
                'url',
                null,
                InputOption::VALUE_REQUIRED,
                'Base URL of OAI-PMH server'
            )->addOption(
                'httpUser',
                null,
                InputOption::VALUE_REQUIRED,
                'Username to access url'
            )->addOption(
                'httpPass',
                null,
                InputOption::VALUE_REQUIRED,
                'Password to access url'
            )->addOption(
                'set',
                null,
                InputOption::VALUE_REQUIRED,
                'Set name to harvest'
            )->addOption(
                'metadataPrefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Metadata prefix to harvest'
            )->addOption(
                'timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'HTTP timeout (in seconds)'
            )->addOption(
                'combineRecords',
                null,
                InputOption::VALUE_NONE,
                'Turn off "one record per file" mode'
            )->addOption(
                'combineRecordsTag',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the XML tag wrapped around multiple records in '
                . "combineRecords\nmode (default = <collection> if this "
                . 'option is omitted)'
            )->addOption(
                'globalSearch',
                null,
                InputOption::VALUE_REQUIRED,
                'Regular expression to replace in raw XML'
            )->addOption(
                'globalReplace',
                null,
                InputOption::VALUE_REQUIRED,
                'String to replace globalSearch regex matches'
            )->addOption(
                'injectDate',
                null,
                InputOption::VALUE_REQUIRED,
                'Inject date from header into specified tag'
            )->addOption(
                'injectId',
                null,
                InputOption::VALUE_REQUIRED,
                'Inject ID from header into specified tag'
            )->addOption(
                'injectSetName',
                null,
                InputOption::VALUE_REQUIRED,
                'Inject setName from header into specified tag'
            )->addOption(
                'injectSetSpec',
                null,
                InputOption::VALUE_REQUIRED,
                'Inject setSpec from header into specified tag'
            )->addOption(
                'idSearch',
                null,
                InputOption::VALUE_REQUIRED,
                'Regular expression to replace in ID'
                . ' (only relevant when injectId is on)'
            )->addOption(
                'idReplace',
                null,
                InputOption::VALUE_REQUIRED,
                'String to replace idSearch regex matches'
            )->addOption(
                'dateGranularity',
                null,
                InputOption::VALUE_REQUIRED,
                '"YYYY-MM-DDThh:mm:ssZ," "YYYY-MM-DD" or "auto" (default)'
            )->addOption(
                'harvestedIdLog',
                null,
                InputOption::VALUE_REQUIRED,
                'Filename (relative to harvest directory)'
                . ' to store log of harvested IDs.'
            )->addOption(
                'autosslca',
                null,
                InputOption::VALUE_NONE,
                'Attempt to autodetect SSL certificate file/path'
            )->addOption(
                'sslcapath',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to SSL certificate authority directory'
            )->addOption(
                'sslcafile',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to SSL certificate authority file'
            )->addOption(
                'nosslverifypeer',
                null,
                InputOption::VALUE_NONE,
                'Disable SSL verification'
            )->addOption(
                'sanitize',
                null,
                InputOption::VALUE_NONE,
                'Strip illegal characters from XML'
            )->addOption(
                'sanitizeRegex',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional regular expression defining XML characters to remove'
            )->addOption(
                'badXMLLog',
                null,
                InputOption::VALUE_REQUIRED,
                'Filename (relative to harvest directory) to log'
                . ' XML fixed by sanitize setting'
            );
    }

    /**
     * Use command-line switches to add/override settings found in the .ini
     * file, if necessary.
     *
     * @param InputInterface $input    Input object
     * @param array          $settings Incoming settings
     *
     * @return array
     */
    protected function updateSettingsWithConsoleOptions(InputInterface $input,
        $settings
    ) {
        $directMapSettings = [
            'url', 'set', 'metadataPrefix', 'timeout', 'combineRecordsTag',
            'injectDate', 'injectId', 'injectSetName', 'injectSetSpec',
            'idSearch', 'idReplace', 'dateGranularity', 'harvestedIdLog',
            'badXMLLog', 'httpUser', 'httpPass', 'sslcapath', 'sslcafile',
            'sanitizeRegex',
        ];
        foreach ($directMapSettings as $setting) {
            if ($value = $input->getOption($setting)) {
                $settings[$setting] = $value;
            }
        }
        $flagSettings = [
            'combineRecords' => ['combineRecords', true],
            'verbose' => ['verbose', true],
            'autosslca' => ['autosslca', true],
            'nosslverifypeer' => ['sslverifypeer', false],
            'sanitize' => ['sanitize', true],
        ];
        foreach ($flagSettings as $in => $details) {
            if ($input->hasOption($in) && $input->getOption($in)) {
                [$out, $val] = $details;
                $settings[$out] = $val;
            }
        }
        return $settings;
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Only set up output writer if not in silent mode:
        if (!$this->silent) {
            $this->setOutputWriter(new ConsoleWriter($output));
        }

        if (!$allSettings = $this->getSettings($input)) {
            return 1;
        }

        // Loop through all the settings and perform harvests:
        $processed = $skipped = $errors = 0;
        foreach ($allSettings as $target => $baseSettings) {
            $settings = $this->updateSettingsWithConsoleOptions(
                $input, $baseSettings
            );
            if (empty($target) || empty($settings)) {
                $skipped++;
                continue;
            }
            $this->writeLine("Processing {$target}...");
            try {
                $this->harvestSingleRepository($input, $output, $target, $settings);
            } catch (\Exception $e) {
                if ($e instanceof OaiException
                    && strtolower($e->getOaiCode()) == 'norecordsmatch'
                ) {
                    $this->writeLine("No new records found.");
                } else {
                    $this->writeLine($e->getMessage());
                    $errors++;
                }
            }
            $processed++;
        }

        // All done.
        if ($processed == 0 && $skipped > 0) {
            $this->writeLine(
                'No valid settings found; '
                . 'please set url and metadataPrefix at minimum.'
            );
            return 1;
        }
        if ($errors > 0) {
            $this->writeLine(
                "Completed with {$errors} error(s) -- "
                . "{$processed} source(s) processed."
            );
            return 1;
        }
        $this->writeLine(
            "Completed without errors -- {$processed} source(s) processed."
        );
        return 0;
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
     * @param InputInterface $input Input object
     *
     * @return array|bool
     */
    protected function getSettings(InputInterface $input)
    {
        $ini = $input->getOption('ini');
        $section = $input->getArgument('target');
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
     * @param InputInterface  $input    Input object
     * @param OutputInterface $output   Output object
     * @param string          $target   Name of repo (used for target directory)
     * @param array           $settings Settings for the harvester.
     *
     * @return void
     * @throws \Exception
     */
    protected function harvestSingleRepository(InputInterface $input,
        OutputInterface $output, $target, $settings
    ) {
        $settings['from'] = $input->getOption('from');
        $settings['until'] = $input->getOption('until');
        $settings['silent'] = false;
        $harvest = $this->factory->getHarvester(
            $target,
            $this->getHarvestRoot(),
            $this->getHttpClient(),
            $settings,
            $output
        );
        $harvest->launch();
    }
}

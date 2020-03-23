<?php
/**
 * Factory for OAI-PMH Harvest Tool
 *
 * PHP version 7
 *
 * Copyright (c) Demian Katz 2010.
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
use Symfony\Component\Console\Output\OutputInterface;
use VuFindHarvest\ConsoleOutput\ConsoleWriter;
use VuFindHarvest\RecordWriterStrategy\RecordWriterStrategyFactory;
use VuFindHarvest\RecordWriterStrategy\RecordWriterStrategyInterface;
use VuFindHarvest\ResponseProcessor\ResponseProcessorInterface;
use VuFindHarvest\ResponseProcessor\SimpleXmlResponseProcessor;

/**
 * Factory for OAI-PMH Harvest Tool
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:oai-pmh Wiki
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class HarvesterFactory
{
    /**
     * Add SSL options to $options if standard files can be autodetected.
     *
     * @param array $options Options to modify.
     *
     * @return void
     */
    protected function addAutoSslOptions(& $options)
    {
        // RedHat/CentOS:
        if (file_exists('/etc/pki/tls/cert.pem')) {
            $options['sslcafile'] = '/etc/pki/tls/cert.pem';
        }
        // Debian/Ubuntu:
        if (file_exists('/etc/ssl/certs')) {
            $options['sslcapath'] = '/etc/ssl/certs';
        }
    }

    /**
     * Get HTTP client options from $settings array
     *
     * @param array $settings Settings
     *
     * @return array
     */
    protected function getClientOptions(array $settings)
    {
        $options = [
            'timeout' => $settings['timeout'] ?? 60,
        ];
        if (isset($settings['autosslca']) && $settings['autosslca']) {
            $this->addAutoSslOptions($options);
        }
        foreach (['sslcafile', 'sslcapath'] as $sslSetting) {
            if (isset($settings[$sslSetting])) {
                $options[$sslSetting] = $settings[$sslSetting];
            }
        }
        if (isset($settings['sslverifypeer']) && !$settings['sslverifypeer']) {
            $options['sslverifypeer'] = false;
        }
        return $options;
    }

    /**
     * Configure the HTTP client
     *
     * @param Client $client   HTTP client
     * @param array  $settings Settings
     *
     * @return Client
     *
     * @throws Exception
     */
    protected function configureClient(Client $client, array $settings)
    {
        $configuredClient = $client ?: new Client();

        // Set authentication, if necessary:
        if (!empty($settings['httpUser']) && !empty($settings['httpPass'])) {
            $configuredClient->setAuth($settings['httpUser'], $settings['httpPass']);
        }

        // Set up assorted client options from $settings array:
        $configuredClient->setOptions($this->getClientOptions($settings));

        return $configuredClient;
    }

    /**
     * Set up directory structure for harvesting.
     *
     * @param string $harvestRoot Root directory containing harvested data.
     * @param string $target      The OAI-PMH target directory to create inside
     * $harvestRoot.
     *
     * @return string
     */
    protected function getBasePath($harvestRoot, $target)
    {
        // Build the full harvest path:
        $basePath = rtrim($harvestRoot, '/') . '/' . rtrim($target, '/') . '/';

        // Create the directory if it does not already exist:
        if (!is_dir($basePath)) {
            if (!mkdir($basePath)) {
                throw new \Exception("Problem creating directory {$basePath}.");
            }
        }

        return $basePath;
    }

    /**
     * Get the communicator.
     *
     * @param Client                     $client    HTTP client
     * @param array                      $settings  Additional settings
     * @param ResponseProcessorInterface $processor Response processor
     * @param string                     $target    Target being configured (used for
     * error messages)
     * @param OutputInterface            $output    Output interface
     *
     * @return Communicator
     */
    protected function getCommunicator(Client $client, array $settings,
        ResponseProcessorInterface $processor, $target,
        OutputInterface $output = null
    ) {
        if (empty($settings['url'])) {
            throw new \Exception("Missing base URL for {$target}.");
        }
        $comm = new Communicator($settings['url'], $client, $processor);
        // We only want the communicator to output messages if we are in verbose
        // mode; communicator messages are considered verbose output.
        if (($settings['verbose'] ?? false)
            && $writer = $this->getConsoleWriter($output, $settings)
        ) {
            $comm->setOutputWriter($writer);
        }
        return $comm;
    }

    /**
     * Get the record XML formatter.
     *
     * @param Communicator    $communicator Communicator
     * @param array           $settings     Additional settings
     * @param OutputInterface $output       Output interface
     *
     * @return RecordXmlFormatter
     */
    protected function getFormatter(Communicator $communicator, array $settings,
        OutputInterface $output = null
    ) {
        // Build the formatter:
        $formatter = new RecordXmlFormatter($settings);

        // Load set names if we're going to need them:
        if ($formatter->needsSetNames()) {
            $loader = $this->getSetLoader($communicator, $settings);
            if ($writer = $this->getConsoleWriter($output, $settings)) {
                $loader->setOutputWriter($writer);
            }
            $formatter->setSetNames($loader->getNames());
        }

        return $formatter;
    }

    /**
     * Get console output writer (if applicable).
     *
     * @param OutputInterface $output   Output interface
     * @param array           $settings OAI-PMH settings
     *
     * @return ConsoleWriter
     */
    protected function getConsoleWriter(?OutputInterface $output, $settings)
    {
        // Don't create a writer if we're in silent mode or have no
        // available output interface.
        return (($settings['silent'] ?? false) || $output === null)
            ? null : new ConsoleWriter($output);
    }

    /**
     * Get XML response processor.
     *
     * @param string $basePath Base path for harvest
     * @param array  $settings OAI-PMH settings
     *
     * @return SimpleXmlResponseProcessor
     */
    protected function getResponseProcessor($basePath, array $settings)
    {
        return new SimpleXmlResponseProcessor($basePath, $settings);
    }

    /**
     * Get the set loader (used to load set names).
     *
     * @param Communicator $communicator API communicator
     * @param array        $settings     OAI-PMH settings
     *
     * @return SetLoader
     */
    protected function getSetLoader(Communicator $communicator, array $settings)
    {
        return new SetLoader($communicator, $settings);
    }

    /**
     * Get state manager
     *
     * @param string $basePath Base path for harvest
     *
     * @return StateManager
     */
    protected function getStateManager($basePath)
    {
        return new StateManager($basePath);
    }

    /**
     * Build the writer support object.
     *
     * @param RecordWriterStrategyInterface $strategy  Writing strategy
     * @param RecordXmlFormatter            $formatter XML record formatter
     * @param array                         $settings  Configuration settings
     *
     * @return RecordWriter
     */
    protected function getWriter(RecordWriterStrategyInterface $strategy,
        RecordXmlFormatter $formatter, array $settings
    ) {
        return new RecordWriter($strategy, $formatter, $settings);
    }

    /**
     * Get the factory for record writer strategies.
     *
     * @return RecordWriterStrategyFactory
     */
    protected function getWriterStrategyFactory()
    {
        return new RecordWriterStrategyFactory();
    }

    /**
     * Get the harvester
     *
     * @param string          $target      Name of source being harvested (used as
     * directory name for storing harvested data inside $harvestRoot)
     * @param string          $harvestRoot Root directory containing harvested data.
     * @param Client          $client      HTTP client
     * @param array           $settings    Additional settings
     * @param OutputInterface $output      Output interface (optional)
     *
     * @return Harvester
     *
     * @throws \Exception
     */
    public function getHarvester($target, $harvestRoot, Client $client = null,
        array $settings = [], OutputInterface $output = null
    ) {
        $basePath = $this->getBasePath($harvestRoot, $target);
        $responseProcessor = $this->getResponseProcessor($basePath, $settings);
        $communicator = $this->getCommunicator(
            $this->configureClient($client, $settings),
            $settings, $responseProcessor, $target, $output
        );
        $formatter = $this->getFormatter($communicator, $settings, $output);
        $strategy = $this->getWriterStrategyFactory()
            ->getStrategy($basePath, $settings);
        $writer = $this->getWriter($strategy, $formatter, $settings);
        $stateManager = $this->getStateManager($basePath);
        $harvester = new Harvester($communicator, $writer, $stateManager, $settings);
        if ($writer = $this->getConsoleWriter($output, $settings)) {
            $harvester->setOutputWriter($writer);
        }
        return $harvester;
    }
}

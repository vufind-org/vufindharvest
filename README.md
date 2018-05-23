[![Build Status](https://travis-ci.org/vufind-org/vufindharvest.svg?branch=master)](https://travis-ci.org/vufind-org/vufindharvest)
VuFindHarvest
=============

Introduction
------------
VuFindHarvest contains OAI-PMH harvesting logic. This is part of the VuFind project
(https://vufind.org) but may be used as a stand-alone tool or incorporated into other
software dealing with metadata harvesting.


Installation
------------
The recommended method for incorporating this library into your project is to use
Composer (http://getcomposer.org). If you wish to use this as a stand-alone tool,
simply clone the repository and run `composer install` or `php composer.phar install`
(depending on your Composer setup) to download dependencies.


Concept
-------
This tool is designed to allow for a pipeline approach to OAI-PMH record processing.
Its job is to harvest metadata from one or more repositories into one or more
directories. It can support a one-file-per-record or a multiple-records-per-file
approach. Records can be manipulated and augmented with the help of certain
configuration options (primarily to copy data from the OAI-PMH header into the
harvested record itself when necessary).

Each directory containing harvested records also includes a last_harvest.txt file
which remembers the most recently harvested record date. This allows the tool to
be re-run on subsequent occasions to perform an incremental update and retrieve
new content.

Interrupted harvests may sometimes be resumed with the help of a last_state.txt
file, that will exist in the harvest directory after an abnormal termination of
the tool.

Deleted records are supported through the creation of ".delete" files containing
the IDs of records that have been removed from the system.


Usage
-----
This package includes a `bin/harvest_oai.php` script which provides a command-line
interface for OAI-PMH harvesting. All harvesting options may be provided at the
command-line, or else a .ini file containing saved options may be loaded using the
`--ini` switch.


### Harvesting without an .ini file

For the most basic harvest, you need to specify the `--url` and `--metadataPrefix`
options and include a target parameter specifying where records should be
harvested. For additional options, run `php bin/harvest_oai.php --help`.

Example:

`php bin/harvest_oai.php --url=http://example.com/oai_server --metadataPrefix=oai_dc my_target_dir`

### Harvesting with an .ini file

When specifying many complex options, or when harvesting multiple repositories
at once, configuring the harvest with an .ini file is the best option. The .ini
option offers more flexibility than the pure command-line option. Note that any
command line options passed to the harvester during an .ini-driven harvest will
override the equivalent settings in the .ini file.

For a full list of .ini options and some example configurations, see the sample
file found in [/etc/oai.ini](https://github.com/vufind-org/vufindharvest/blob/master/etc/oai.ini).

If you specify a parameter following the option list when using an .ini file,
only the section of the configuration file matching the parameter will be used,
and records will be harvested to a directory with a matching name. For example:

`php bin/harvest_oai.php --ini=/etc/oai.ini OJS`

If you omit the parameter, all sections of the .ini file will be harvested in
sequence.


Architecture
------------
If you wish to incorporate this code into another project, or extend it to
support more options, here are the most important top-level classes:

* [VuFindHarvester\OaiPmh\HarvesterConsoleRunner](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/HarvesterConsoleRunner.php) - Provides command-line interface around VuFindHarvester\OaiPmh\Harvester
* [VuFindHarvester\OaiPmh\HarvesterFactory](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/HarvesterFactory.php) - Factory class to create VuFindHarvester\OaiPmh\Harvester objects with all dependencies injected
* [VuFindHarvester\OaiPmh\Harvester](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/Harvester.php) - Class to perform a single harvest of a single OAI-PMH repository.

Here are key dependencies used by VuFindHarvester\OaiPmh\Harvester:

* [VuFindHarvester\OaiPmh\Communicator](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/Communicator.php) - Wrapper around the HTTP communication used by the OAI-PMH protocol (also uses a [response processor](https://github.com/vufind-org/vufindharvest/tree/master/src/ResponseProcessor) to manipulate retrieved results)
* [VuFindHarvester\OaiPmh\RecordWriter](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/RecordWriter.php) - Class to manage writing OAI-PMH records to disk; utilizes one of the available [record writer strategies](https://github.com/vufind-org/vufindharvest/tree/master/src/RecordWriterStrategy)
* [VuFindHarvester\OaiPmh\RecordXmlFormatter](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/RecordXmlFormatter.php) - Class to process/fix/augment harvested XML data prior to writing it to disk
* [VuFindHarvester\OaiPmh\SetLoader](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/SetLoader.php) - Class to retrieve a list of set information from an OAI-PMH server (used for certain types of optional XML augmentation)
* [VuFindHarvester\OaiPmh\StateManager](https://github.com/vufind-org/vufindharvest/blob/master/src/OaiPmh/StateManager.php) - Class for managing harvest state (last harvest date, current resumption token) on disk to assist incremental harvests and recovery from problems

Several classes make use of the traits and classes in the [VuFindHarvester\ConsoleOutput](https://github.com/vufind-org/vufindharvest/tree/master/src/ConsoleOutput)
namespace to help with standard status output tasks.


History
-------
See [CHANGELOG.md](https://github.com/vufind-org/vufindharvest/blob/master/CHANGELOG.md)

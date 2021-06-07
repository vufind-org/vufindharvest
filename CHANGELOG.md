# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 4.1.0 - 2021-06-07

### Added

- The VuFindHarvest\Exception\OaiException class, for finer-grained error handling.

### Changed

- The minimum PHP version requirement has been raised to 7.3.
- The noRecordsMatch error is no longer treated as a fatal problem.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- The failure to harvest from one OAI source no longer causes the entire batch to fail.

## 4.0.1 - 2020-03-23

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Improved help message for --ini option.

## 4.0.0 - 2020-03-18

### Added

- VuFindHarvest\OaiPmh\HarvesterCommand class (for Symfony\Console integration).

### Changed

- Raised minimum PHP requirement to version 7.2.
- Replaced Laminas\Console with Symfony\Console as console interaction framework.
- VuFindHarvest\ConsoleOutput\ConsoleWriter class is now a wrapper around Symfony\Component\Console\Output\OutputInterface (which also impacts some of the internals of VuFindHarvest\OaiPmh\HarvesterFactory).

### Deprecated

- Nothing.

### Removed

- VuFindHarvest\OaiPmh\HarvesterConsoleRunner class (for Laminas\Console integration).

### Fixed

- Nothing.

## 3.0.0 - 2020-01-27

### Added

- Nothing.

### Changed

- Updated Zend dependencies to use Laminas equivalents.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.4.1 - 2019-09-13

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Bug: each batch of IDs in log file was missing trailing line break.

## 2.4.0 - 2018-05-23

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- PHP 5 support.

### Fixed

- Nothing.

## 2.3.0 - 2017-04-06

### Added

- New globalSearch / globalReplace parameters.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- PHP 5.4/5.5 support.

### Fixed

- Bug: xmlns namespace attributes injected incorrectly.

## 2.2.0 - 2016-12-16

### Added

- New sanitizeRegex setting to optionally allow override of default XML sanitization regular expression.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Bug: authentication credentials cleared between requests.

## 2.1.0 - 2016-07-14

### Added

- Better support for SSL certificate configuration

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 2.0.0 - 2016-07-13

### Added

- Expanded command-line functionality.
- Documentation.
- More complete test suite.

### Changed

- Complete rewrite of code for better separation of concerns.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.1.0 - 2016-06-14

### Added

- Simple stand-alone console interface

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.0 - 2016-06-08
Initial extraction of library code from the [VuFind](https://github.com/vufind-org/vufind) project

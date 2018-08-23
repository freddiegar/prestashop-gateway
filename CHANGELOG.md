# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.4.2] - 2018-08-23

### Updated
- Update dependencies guzzle/guzzle from 5.3.2 => 5.3.3
- Update README file with [mailtrap.io](https://mailtrap.io/)
- Update format name logfile, it is: \[dev|prod\]_YYYYMMDD_placetopayment.log
- Update commands in Makefile
- Update max version's PrestaShop supported: PS 1.7.4.2

### Fixed
- Fix translations in: `es` and `gb` locales
- Fix bug getting order by cart id, fail if order not exist

### Removed
- Stock re-inject option setup in PS 1.6, deprecated in PS 1.5

## [3.4.0] - 2018-07-25

### Added
- Add payment method selector to restrict in redirection page
- Add currency validator before request PlacetoPay

### Updated
- Change alerts, now are show in top of page
- Save errorCode in log database as objectId and updated error codes
- Improve logs when connection to service failed
- Show more configuration in sonda request

## [3.3.0] - 2018-07-19

### Added
- Exception and any others errors are visible from PS back-office System Logs
- Added object type in errors save in logs prestashop

### Updated
- Minimum version support now is PS 1.6.0.5
- Change PaymentLogger::log function
- Update error code, catalog table create

## [3.2.7] - 2018-07-17

### Updated
- Simple fix path applied, improve support
- Not overwrite default country in docker installation
- Allow installation in default country (gb)

### Fixed
- Fix cs
- Fix log path in PS >= 1.7.4.0
- Fix guzzle in PS >= 1.7.4.0, downgrade from 6.3.3 to 5.3.2

## [3.2.6] - 2018-07-13

### Fixed
- Fix message error (in database) on failed transaction, before it is not was updated
- Fix translations, index error in files

### Updated
- Update dependencies dnetix/redirection from 0.4.3 => 0.4.5 (Add extra currencies)
- Added code sniffer validations

## [3.2.5] - 2018-05-15

### Fixed
- Fix return page, now it depends of status payment

## [3.2.4] - 2018-04-27

### Added
- Allowed set a Custom Connection URL to connect to payment service in PlacetoPay

### Fixed
- Fix bug in Windows System with Apache Server installed (Separator)
- Fix bug in English translations files
- Fix bug on update status, add validation to request object

### Updated
- Update message trace on development and improve code type hint and vars name
- Remove translations not used
- Update dependencies to stable versions, thus:
    psr/http-message (1.0.1)
    guzzlehttp/psr7 (1.4.2)
    guzzlehttp/promises (v1.3.1)
    guzzlehttp/guzzle (6.3.3)
    dnetix/redirection (0.4.3)

## [3.1.0] - 2018-03-11

### Added
- Add makefile with docker
- Add validation in notification to signature
- Add extra security, to show setup is necesary send last 5 characteres of login to show data
- Add skipResult setup to skip last screen in payment process on payment
- Add Placetopay brand in PS >= 1.7 in payment options form
- Add validation to execute sonda process, from browser not is available, only CLI

### Fixed
- Fix bug in way to get URL base
- Fix bug when transaction not is approved not update reason and reaso…
- Fix bug updating description in payments rejected (error in bd)
- Fix bug in value assigned of stock reinject on update
- Fix errors when module is re-install, catch error generate by rename columns
- Fix error when module is executed but it is not installed yet (from sonda process)
- Fix bug in installation on PS 1.7.2.5, logo.png was change path
- Fix Skip class when some are not found in PS 1.7 loader

### Updated
- Update dependency redirection from 0.4.1 to 0.4.2
- Update dependencies guzzle from 6.2 to 6.3
- Update message trace on development
- Update translation changing Place to Pay -> PlacetoPay

### Created
- Create CONTRIBUTING.md
- Create LICENSE

## [3.0.2] - 2018-01-10

### Fixed
- Fix bug in notification process, column name error

## [3.0.1] - 2017-12-13

### Fixed
- Fix bug in utf8 translations in spanish in some installations in Prestashop < 1.7
 
## [3.0.0] - 2017-12-06

### Added
- Add compatibility with Prestashop >= 1.7

## [2.6.4] - 2017-12-01

### Fixed
- Fixed bug in Windows Server Systems
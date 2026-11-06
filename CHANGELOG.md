# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Support for Symfony 7.4 and 8.x
- Document WordPress 5.5 as the minimum version in the README
- PHPUnit integration tests and a GitHub Actions matrix across Symfony and PHP versions, with phpunit/phpunit (`^10.5 || ^11.5 || ^12.0`) and symfony/yaml (`^6.4 || ^7.4 || ^8.0`) as dev dependencies
- Contract tests that run on WordPress's own hook API through the roots/wordpress-no-content dev dependency, a lowest-dependencies CI job, and PHPUnit reporting of suppressed deprecations

### Changed

- Test against WordPress 5.5: lower the roots/wordpress-no-content dev constraint from `>=6.0` to `>=5.5` and disable the roots/wordpress-core-installer Composer plugin

## [1.2.0] - 2025-05-22

### Changed

- Refactored container configuration to mimic Symfony kernel more closely

## [1.1.0] - 2025-03-28

### Changed

- Make container available without automatically booting on load

[unreleased]: https://github.com/achttienvijftien/service-container/compare/1.2.0..main

[1.2.0]: https://github.com/achttienvijftien/service-container/compare/1.1.0..1.2.0

[1.1.0]: https://github.com/achttienvijftien/service-container/compare/1.0.0..1.1.0

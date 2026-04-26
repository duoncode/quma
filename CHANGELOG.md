# Changelog

## [Unreleased](https://github.com/duoncode/quoma/compare/0.1.1...HEAD)

### Added

- Added explicit `Database` lifecycle helpers: `connected()`, `disconnect()`, `reconnect()`, `ping()`, and `reset()`.
- Added internal connection timestamp tracking in `Database` to support long-running PHP process integrations.

### Changed

- Changed MySQL migration dry runs to print a plan without mutating the database.
- Changed non-default migration namespaces to record applied migrations as `namespace:basename`.

### Fixed

- Fixed custom migration metadata table and column names when reading and recording applied migrations.
- Fixed dynamic SQL folder and script resolution to reject invalid path segments.

## [0.1.1](https://github.com/duoncode/quma/releases/tag/0.1.1) (2026-02-07)

### Changed

- Reworked template execution for `*.tpql` and PHP migrations to load files via `include`/`require` instead of evaluating raw file contents.
- Improved SQL and migration directory parsing for nested and namespaced configurations, including safer handling of invalid entries.
- Hardened named-parameter preparation for template queries to keep only placeholders that are actually present in rendered SQL.

### Fixed

- Added stricter migration loading validation with clearer failures for missing files and invalid migration objects.
- Added a defensive runtime guard when reading the PDO connection before initialization.

## [0.1.0](https://github.com/duoncode/quma/releases/tag/0.1.0) (2026-01-31)

Initial release.

### Added

- No-ORM database library for executing raw SQL files
- SQL file organization and query management
- Database migration support
- PDO-based connection handling

# Changelog

## [Unreleased](https://github.com/duoncode/quma/compare/0.1.1...HEAD)

### Added

- Added driver-aware static placeholders with `[::name::]` syntax for trusted SQL configuration fragments such as table prefixes and schema names.
- Added configurable static placeholder delimiters via `Delimiters` and `Connection::delimiters()`.
- Added static placeholder support to `.sql` queries, `.tpql` query templates, `.sql` migrations, and `.tpql` migrations.
- Added optional `.tpql` query template caching via `Connection::cache()`.
- Added `QUMA_DEBUG`, `QUMA_DEBUG_PRINT`, `QUMA_DEBUG_TRANSLATED`, and `QUMA_DEBUG_INTERPOLATED` for environment-controlled SQL debugging.
- Added explicit `Database` lifecycle helpers: `connected()`, `disconnect()`, `reconnect()`, `ping()`, and `reset()`.
- Added `Database::debug()` and `Database::debugging()` to toggle SQL debug output per database handle.
- Added internal connection timestamp tracking in `Database` to support long-running PHP process integrations.
- Added `Query::first()` for stable first-row reads and `Query::fetch()` for cursor-style reads.
- Added optional row hydration for `one()`, `first()`, `fetch()`, `all()`, and `lazy()` through class-string targets, resolver closures, `#[Column]`, `Hydratable`, and hydration-specific exceptions.

### Changed

- Changed `Connection` to require only DSN and SQL directories in the constructor. Optional PDO, migration, placeholder, cache, and fetch mode settings now use fluent methods.
- Changed MySQL migration dry runs to print a plan without mutating the database.
- Changed non-default migration namespaces to record applied migrations as `namespace:basename`.
- Changed the default query fetch mode from `PDO::FETCH_BOTH` to `PDO::FETCH_ASSOC`.
- Changed query terminal method signatures so the optional hydration map is the first argument and the per-call fetch mode is the second argument or `fetchMode` named argument.
- Changed `Query::one()` to require exactly one result and throw `UnexpectedResultCountException` for empty or multi-row results.
- Removed `Connection::print()`, `Connection::prints()`, and `Database::print()` in favor of `QUMA_DEBUG_PRINT`.

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

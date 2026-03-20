---
title: API reference
---

# API reference

This page summarizes the main public types that application code works with directly.

## `Duon\Quma\Database`

The main entry point for query execution.

### Constructor

```php
new Database(Connection $conn)
```

### Key methods

- `connect(): static` opens the PDO connection lazily
- `getConn(): PDO` returns the PDO instance
- `quote(string $value): string` proxies to `PDO::quote()`
- `begin(): bool` starts a transaction
- `commit(): bool` commits the current transaction
- `rollback(): bool` rolls back the current transaction
- `execute(string $query, mixed ...$args): Query` runs ad-hoc SQL through the same query pipeline as file-based queries
- `getFetchMode(): int` returns the configured default fetch mode
- `getPdoDriver(): string` returns the active PDO driver name
- `getSqlDirs(): array` returns the resolved SQL directory list
- `print(bool $print = false): bool` gets or sets debug printing

### Dynamic access

`Database` uses magic property access to resolve folders.

```php
$db->users;
```

This returns a `Folder` instance for the `users` SQL directory.

## `Duon\Quma\Folder`

Represents one SQL folder.

### Dynamic access

- `$db->users->byId` returns a `Script`
- `$db->users->byId(42)` returns a `Query`

If the script file does not exist, `Folder` throws `RuntimeException`.

## `Duon\Quma\Script`

Wraps a static `.sql` file or a template `.tpql` file.

### Key methods

- `__invoke(mixed ...$args): Query`
- `invoke(mixed ...$args): Query`

Most application code uses a `Script` implicitly through `$db->folder->queryName(...)`.

## `Duon\Quma\Query`

Represents a prepared query.

### Execution methods

- `one(?int $fetchMode = null): ?array`
- `all(?int $fetchMode = null): array`
- `lazy(?int $fetchMode = null): Generator`
- `run(): bool`
- `len(): int`

### Debug helpers

- `interpolate(): string` returns a best-effort interpolated SQL string for debugging
- `__toString(): string` proxies to `interpolate()`

## `Duon\Quma\Environment`

Provides the runtime context for migration commands and PHP migrations.

### Public properties

- `$conn`
- `$driver`
- `$showStacktrace`
- `$table`
- `$columnMigration`
- `$columnApplied`
- `$db`
- `$options`

### Key methods

- `getMigrations(): array|false`
- `checkIfMigrationsTableExists(Database $db): bool`
- `getMigrationsTableDDL(): string|false`

## `Duon\Quma\MigrationInterface`

Implemented by PHP migrations.

```php
interface MigrationInterface
{
    public function run(Environment $env): void;
}
```

## `Duon\Quma\Commands`

Factory for the bundled CLI commands.

### Static factory

```php
Commands::get(array|Connection $conn, array $options = []): Duon\Cli\Commands
```

Pass either one `Connection` or an array of named connections.

## Internal helper types you will see in the codebase

These types are part of the public namespace, but most application code does not need to instantiate them directly:

- `Args`
- `ArgType`
- `PreparedQuery`
- `Util`

---
title: Connection reference
---

# Connection reference

`Connection` stores the configuration that Quma uses to resolve SQL files, migrations, and PDO settings.

## Constructor

```php
new Connection(
    string $dsn,
    string|array $sql,
    string|array|null $migrations = null,
    ?string $username = null,
    ?string $password = null,
    array $options = [],
    int $fetchMode = PDO::FETCH_BOTH,
    bool $print = false,
)
```

## Parameters

### `$dsn`

The PDO DSN. Quma extracts the driver name from the DSN prefix and verifies that the driver exists in `PDO::getAvailableDrivers()`.

If the driver is not available, `Connection` throws `RuntimeException`.

### `$sql`

Defines the SQL directories. Supported formats:

- one directory string
- a flat list of directories
- a driver map using `sqlite`, `mysql`, `pgsql`, and `all`
- mixed arrays that combine the formats above

All configured paths must already exist. Otherwise Quma throws `ValueError`.

### `$migrations`

Defines the migration directories. Supported formats:

- `null`
- one directory string
- a flat list of directories
- a namespaced map such as `['default' => '/path/to/migrations']`

If the array is associative and not a driver map, Quma treats it as namespaced migration configuration.

### `$username`, `$password`, `$options`

These values are passed to PDO when `Database` opens the connection.

### `$fetchMode`

The default fetch mode for `Query::one()`, `Query::all()`, and `Query::lazy()` when you do not pass a fetch mode explicitly.

### `$print`

Enables query printing for debugging. You can also toggle it later through `print(true)`.

## Public properties

`Connection` exposes these readonly properties:

- `$dsn`
- `$driver`
- `$username`
- `$password`
- `$options`
- `$fetchMode`

## SQL directory methods

### `sql(): array`

Returns the resolved SQL directory list.

### `addSqlDirs(array|string $sql): void`

Prepends more SQL directories to the existing list.

This method supports the same input formats as the constructor.

## Migration directory methods

### `migrations(): array`

Returns either:

- a flat list of directories
- a namespaced map of directories

### `addMigrationDir(string $migrations): void`

Adds a migration directory to a flat migration configuration.

If the connection uses namespaced migrations, this method does nothing.

## Migration metadata naming

### `setMigrationsTable(string $table): void`

Sets the migrations table name.

Validation rules:

- for SQLite and MySQL, only letters, numbers, and underscores are allowed
- for PostgreSQL, one optional schema prefix is allowed, for example `public.migrations`

### `setMigrationsColumnMigration(string $column): void`

Sets the migration name column.

### `setMigrationsColumnApplied(string $column): void`

Sets the applied-at column.

### `migrationsTable(): string`

Returns the validated migrations table name or throws `ValueError`.

### `migrationsColumnMigration(): string`

Returns the validated migration name column or throws `ValueError`.

### `migrationsColumnApplied(): string`

Returns the validated applied-at column or throws `ValueError`.

## Query printing

`Connection` uses the shared `print()` helper.

```php
$conn->print(true);
```

That flag is passed into `Database` and controls whether `Query` prints an interpolated debug version of the SQL when it is created.

## Current limitation

`Connection` exposes setters for custom migrations table and column names, and Quma validates those names when it builds the environment and DDL.

However, the migration runner currently records applied migrations with a hardcoded insert into `migrations (migration)`. Do not rely on custom migration metadata names for production migration runs until that behavior is aligned.

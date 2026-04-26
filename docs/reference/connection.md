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
    array $placeholders = [],
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

### `$placeholders`

Defines static `[::name::]` placeholder replacements. The top-level `all` scope applies to every driver. A driver-specific scope such as `sqlite`, `mysql`, or `pgsql` overrides `all` for that driver.

```php
$conn = new Connection(
    'mysql:host=localhost;dbname=app',
    __DIR__ . '/sql',
    placeholders: [
        'all' => ['prefix' => ''],
        'pgsql' => ['prefix' => 'cms.'],
        'mysql' => ['prefix' => 'cms_'],
    ],
);
```

Static placeholder names must match `[A-Za-z_][A-Za-z0-9_.:-]*`. Values must be strings and are inserted as raw SQL text. Quma does not quote or escape them.

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

## Query cache methods

### `cacheDir(?string $cacheDir = null): ?string`

Gets or sets the cache directory for compiled `.tpql` query templates.

```php
$conn->cacheDir(__DIR__ . '/var/cache/quma');
```

The directory must already exist, must be a directory, and must be writable. Quma does not create it automatically.

When configured, Quma writes compiled `.tpql` query templates to this directory and reuses them on later invocations. The cache does not apply to `.sql` files, migrations, or direct SQL passed to `Database::execute()`.

Keep this directory outside the public web root. The files are generated PHP templates and can be deleted safely; Quma regenerates them when needed. Cache file names include the source file metadata, active driver, and resolved static placeholder map, so source or configuration changes create a new cache file.

## Static placeholder methods

### `placeholders(): array`

Returns the static placeholder map resolved for the active driver.

### `applyPlaceholders(string $source, string $path, bool $isTemplate = false): string`

Applies static placeholders to SQL or template source. This method is used internally by file-based queries and migrations.

### `assertNoTemplatePlaceholders(string $source, string $path): void`

Throws when rendered template output still contains `[::...::]` text. This catches unsupported static placeholders generated from PHP code blocks.

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

Quma uses these names when it creates the metadata table, checks applied migrations, and records newly applied migrations. For PostgreSQL, a schema-qualified table name such as `public.migrations` is supported.

## Query printing

`Connection` uses the shared `print()` helper.

```php
$conn->print(true);
```

That flag is passed into `Database` and controls whether `Query` prints an interpolated debug version of the SQL when it is created.

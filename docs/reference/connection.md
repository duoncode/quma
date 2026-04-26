---
title: Connection reference
---

# Connection reference

`Connection` stores the configuration that Quma uses to resolve SQL files, migrations, placeholders, and PDO settings. Create it with the required DSN and SQL directory configuration, then add optional settings through fluent methods.

## Constructor

```php
new Connection(string $dsn, string|array $sql)
```

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

## Example

```php
use Duon\Quma\Connection;
use PDO;

$conn = new Connection(
    'sqlite:' . __DIR__ . '/app.sqlite',
    __DIR__ . '/sql',
)
    ->migrations(__DIR__ . '/migrations')
    ->fetch(PDO::FETCH_ASSOC)
    ->placeholders([
        'all' => ['prefix' => ''],
        'pgsql' => ['prefix' => 'cms.'],
        'mysql' => ['prefix' => 'cms_'],
    ]);
```

Configure a connection before you create or connect a `Database`. PDO settings are read when `Database` opens the PDO connection. Query printing is copied from `Connection` when you construct `Database`; use `Database::print()` to change printing on an existing database handle.

## Basic accessors

### `dsn(): string`

Returns the configured PDO DSN.

### `driver(): string`

Returns the detected PDO driver name.

## PDO configuration

### `credentials(?string $username, ?string $password = null): static`

Sets the username and password passed to PDO.

### `username(): ?string`

Returns the configured PDO username.

### `password(): ?string`

Returns the configured PDO password.

### `options(array $options): static`

Replaces the PDO options array passed to PDO.

### `option(int $attribute, mixed $value): static`

Sets one PDO option.

### `pdoOptions(): array`

Returns the configured PDO options array.

### `fetch(int $fetchMode): static`

Sets the default fetch mode for unmapped `Query::one()`, `Query::all()`, and `Query::lazy()` calls when you do not pass a fetch mode explicitly.

The default is `PDO::FETCH_ASSOC`. Mapped calls that hydrate rows into objects fetch associative rows by default and reject explicit non-associative fetch modes.

### `fetchMode(): int`

Returns the configured default fetch mode.

## SQL directory methods

### `sql(): array`

Returns the resolved SQL directory list.

### `addSql(array|string $sql): static`

Prepends more SQL directories to the existing list.

This method supports the same input formats as the constructor.

## Query cache methods

### `cache(string $cacheDir): static`

Sets the cache directory for compiled `.tpql` query templates.

```php
$conn->cache(__DIR__ . '/var/cache/quma');
```

The directory must already exist, must be a directory, and must be writable. Quma does not create it automatically.

When configured, Quma writes compiled `.tpql` query templates to this directory and reuses them on later invocations. The cache does not apply to `.sql` files, migrations, or direct SQL passed to `Database::execute()`.

Keep this directory outside the public web root. The files are generated PHP templates and can be deleted safely; Quma regenerates them when needed. Cache file names include the source file metadata, active driver, and resolved static placeholder map, so source or configuration changes create a new cache file.

### `noCache(): static`

Clears the configured cache directory.

### `cacheDir(): ?string`

Returns the resolved cache directory, or `null` when no cache directory is configured.

## Static placeholder methods

### `placeholders(array $placeholders): static`

Defines static `[::name::]` placeholder replacements. The top-level `all` scope applies to every driver. A driver-specific scope such as `sqlite`, `mysql`, or `pgsql` overrides `all` for that driver.

```php
$conn->placeholders([
    'all' => ['prefix' => ''],
    'pgsql' => ['prefix' => 'cms.'],
    'mysql' => ['prefix' => 'cms_'],
]);
```

Static placeholder names must match `[A-Za-z_][A-Za-z0-9_.:-]*`. Values must be strings and are inserted as raw SQL text. Quma does not quote or escape them.

### `placeholderValues(): array`

Returns the static placeholder map resolved for the active driver.

### `applyPlaceholders(string $source, string $path, bool $isTemplate = false): string`

Applies static placeholders to SQL or template source. This method is used internally by file-based queries and migrations.

### `assertNoTemplatePlaceholders(string $source, string $path): void`

Throws when rendered template output still contains `[::...::]` text. This catches unsupported static placeholders generated from PHP code blocks.

## Migration directory methods

### `migrations(string|array $migrations): static`

Sets the migration directories. Supported formats:

- one directory string
- a flat list of directories
- a namespaced map such as `['default' => '/path/to/migrations']`

If the array is associative and not a driver map, Quma treats it as namespaced migration configuration.

### `migrationDirs(): array`

Returns either:

- a flat list of directories
- a namespaced map of directories

### `addMigration(string $migrations): static`

Adds a migration directory to a flat migration configuration.

If the connection uses namespaced migrations, this method throws `ValueError`. Use `migrationNamespace()` for namespaced migrations.

### `migrationNamespace(string $namespace, string|array $dirs): static`

Adds or replaces one namespaced migration directory entry.

```php
$conn
    ->migrationNamespace('default', __DIR__ . '/migrations/core')
    ->migrationNamespace('install', __DIR__ . '/migrations/install');
```

If the connection already has flat migration directories, this method throws `ValueError`.

## Migration metadata naming

### `migrationTable(string $table): static`

Sets the migrations table name.

Validation rules:

- for SQLite and MySQL, only letters, numbers, and underscores are allowed
- for PostgreSQL, one optional schema prefix is allowed, for example `public.migrations`

### `migrationColumns(string $migration, string $applied = 'applied'): static`

Sets the migration name column and applied-at column.

### `migrationsTable(): string`

Returns the validated migrations table name.

### `migrationsColumnMigration(): string`

Returns the validated migration name column.

### `migrationsColumnApplied(): string`

Returns the validated applied-at column.

Quma uses these names when it creates the metadata table, checks applied migrations, and records newly applied migrations. For PostgreSQL, a schema-qualified table name such as `public.migrations` is supported.

## Query printing

### `print(bool $print): static`

Enables or disables query printing for `Database` instances created after this call.

```php
$conn->print(true);
```

### `prints(): bool`

Returns whether query printing is enabled on the connection.

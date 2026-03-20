---
title: PHP migrations
---

# PHP migrations

Use a PHP migration when SQL alone is not enough. A PHP migration can branch on the driver, run multiple queries, inspect results, and reuse application logic.

## Contract

A PHP migration file must return an object that implements `Duon\Quma\MigrationInterface`.

The interface is:

```php
<?php

declare(strict_types=1);

namespace Duon\Quma;

interface MigrationInterface
{
    public function run(Environment $env): void;
}
```

## Minimal example

```php
<?php

declare(strict_types=1);

use Duon\Quma\Environment;
use Duon\Quma\MigrationInterface;

return new class () implements MigrationInterface {
    public function run(Environment $env): void
    {
        $env->db->execute(
            'CREATE TABLE users (id integer primary key, email text not null)',
        )->run();
    }
};
```

## Environment object

Quma passes one `Environment` instance into `run()`.

The migration can read these public properties:

- `$env->conn` as the active `Connection`
- `$env->db` as the active `Database`
- `$env->driver` as the PDO driver name
- `$env->showStacktrace` as the CLI stacktrace flag
- `$env->table` as the migrations table name
- `$env->columnMigration` as the migration name column
- `$env->columnApplied` as the applied-at column
- `$env->options` as the options array passed into the command setup

## Driver-specific logic

A PHP migration can branch on the current driver.

```php
<?php

declare(strict_types=1);

use Duon\Quma\Environment;
use Duon\Quma\MigrationInterface;

return new class () implements MigrationInterface {
    public function run(Environment $env): void
    {
        switch ($env->driver) {
            case 'sqlite':
                $env->db->execute('ALTER TABLE users ADD COLUMN created_at text')->run();
                break;

            case 'pgsql':
                $env->db->execute(
                    'ALTER TABLE users ADD COLUMN created_at timestamp with time zone',
                )->run();
                break;

            case 'mysql':
                $env->db->execute('ALTER TABLE users ADD COLUMN created_at timestamp')->run();
                break;
        }
    }
};
```

## When to choose PHP over SQL or TPQL

Prefer a `.php` migration when you need to:

- run conditional logic that is easier to express in PHP than in templated SQL
- inspect existing data before choosing the next step
- execute several database operations with intermediate checks
- branch heavily by driver

If you only need to include or exclude a few SQL fragments, a `.tpql` migration is usually simpler.

## Failure behavior

If a PHP migration throws, the migration run stops.

For SQLite and PostgreSQL, Quma rolls back the surrounding transaction. For MySQL, already applied migrations remain applied because the migration runner does not wrap MySQL migrations in the same transaction flow.

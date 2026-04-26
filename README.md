# Quma

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/274d61ae59344c48868d88da2acd6a5c)](https://app.codacy.com/gh/duoncode/quma/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/274d61ae59344c48868d88da2acd6a5c?branch=main)](https://app.codacy.com/gh/duoncode/quma/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
[![Psalm level](https://shepherd.dev/github/duoncode/quma/level.svg?)](https://duon.sh/quma)
[![Psalm coverage](https://shepherd.dev/github/duoncode/quma/coverage.svg?)](https://shepherd.dev/github/duoncode/quma)

Quma is a no-ORM database library for PHP. You store SQL in files, group those files in folders, and execute them through a small PDO-backed API. Quma also ships with template queries and a migration runner.

## Requirements

Quma currently requires:

- PHP 8.5 or newer
- `ext-json`
- `ext-pdo`
- `ext-readline`

## Install

```bash
composer require duon/quma
```

## Quickstart

Create a SQL directory structure like this:

```text
sql/
  users/
    byId.sql
    list.sql
```

Add a query file:

```sql
SELECT id, email FROM users WHERE id = ?;
```

Then configure Quma and run the query:

```php
<?php

declare(strict_types=1);

use Duon\Quma\Connection;
use Duon\Quma\Database;

$conn = (new Connection(
    'sqlite:' . __DIR__ . '/app.sqlite',
    __DIR__ . '/sql',
))->migrations(__DIR__ . '/migrations');

$db = new Database($conn);

$user = $db->users->byId(1)->one();
$users = $db->users->list()->all();
```

Quma maps directories to properties and files to methods:

- `sql/users/byId.sql` becomes `$db->users->byId()`
- `sql/users/list.sql` becomes `$db->users->list()`

## What Quma provides

- SQL-file based queries with positional or named parameters
- static `[::name::]` placeholders for trusted driver-aware configuration fragments
- PDO-backed execution with `one()`, `all()`, `lazy()`, `run()`, and `len()`
- PHP-powered SQL templates via `.tpql` files
- multiple SQL directories with driver-specific overrides
- migration commands for `.sql`, `.tpql`, and `.php` migrations
- optional query printing for debugging

## Documentation

Start with the docs in [`docs/index.md`](docs/index.md).

Recommended pages:

- [Getting started](docs/getting-started.md)
- [Query files](docs/query-files.md)
- [Parameters and results](docs/parameters-and-results.md)
- [Templates](docs/templates.md)
- [Migrations overview](docs/migrations/overview.md)
- [CLI](docs/cli.md)
- [Testing](docs/testing.md)

## Testing

Quma runs against SQLite by default and can also run against MySQL and PostgreSQL when you provide test databases.

```bash
composer test
composer test:sqlite
composer test:mysql
composer test:pgsql
composer test:all
```

For database setup and environment variables, see [docs/testing.md](docs/testing.md).

## License

This project is licensed under the [MIT license](LICENSE.md).

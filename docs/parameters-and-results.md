---
title: Parameters and results
---

# Parameters and results

Quma binds parameters through PDO and returns a `Query` object that you can execute in several ways.

## Positional parameters

Use positional arguments with `?` placeholders.

```sql
SELECT id, email FROM users WHERE id = ?;
```

```php
$user = $db->users->byId(42)->one();
```

You can also pass the positional arguments as a list inside one array.

```php
$user = $db->users->byId([42])->one();
```

## Named parameters

Use one associative array for named placeholders.

```sql
SELECT id, email FROM users WHERE email = :email;
```

```php
$user = $db->users->byEmail([
    'email' => 'someone@example.com',
])->one();
```

## Supported parameter types

Quma supports these parameter value types:

- `bool`
- `int`
- `string`
- `null`
- `array`

Arrays are JSON-encoded before binding.

If you pass any other type, Quma throws `InvalidArgumentException`.

## Fetch one row

Use `one()` to fetch a single row.

```php
$user = $db->users->byId(42)->one();
```

`one()` returns either:

- an array when a row exists
- `null` when no row exists

If you call `one()` multiple times on the same `Query` instance, Quma continues fetching from the already executed statement.

```php
$query = $db->users->list();

$first = $query->one();
$second = $query->one();
```

## Fetch all rows

Use `all()` to fetch every row at once.

```php
$users = $db->users->list()->all();
```

`all()` executes the statement and returns an array of rows.

## Stream rows lazily

Use `lazy()` when you want a generator.

```php
foreach ($db->users->list()->lazy() as $user) {
    // ...
}
```

This is useful when you want to process rows one by one.

## Run write queries

Use `run()` for statements where you only care whether execution succeeded.

```php
$db->users->delete([
    'id' => 42,
])->run();
```

`run()` returns `bool`.

## Read row counts

Use `len()` to return `PDOStatement::rowCount()`.

```php
$count = $db->users->delete([
    'id' => 42,
])->len();
```

The exact row count depends on the PDO driver. For example, SQLite may return `0` for statements where other drivers report an affected row count.

## Fetch modes

Quma uses the fetch mode from `Connection` by default. The default is `PDO::FETCH_BOTH`.

```php
use PDO;

$conn = (new Connection(
    'sqlite:' . __DIR__ . '/app.sqlite',
    __DIR__ . '/sql',
))->fetch(PDO::FETCH_ASSOC);
```

You can also override the fetch mode per call.

```php
use PDO;

$user = $db->users->byId(42)->one(PDO::FETCH_ASSOC);
$users = $db->users->list()->all(PDO::FETCH_ASSOC);
```

## Execute direct SQL

`Database::execute()` accepts the SQL string and the same argument styles as file-based queries.

```php
$total = $db->execute(
    'SELECT count(*) AS total FROM users WHERE active = :active',
    ['active' => true],
)->one();
```

## Debug interpolated SQL

`Query` implements `__toString()`. Converting it to a string returns an interpolated debug representation of the SQL.

```php
$query = $db->users->byEmail([
    'email' => 'someone@example.com',
]);

$sql = (string) $query;
```

This interpolation is intended for debugging, not for constructing executable SQL.

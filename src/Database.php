<?php

declare(strict_types=1);

namespace Conia\Quma;

use Conia\Quma\Connection;
use PDO;
use RuntimeException;

/** @psalm-api */
class Database
{
    use GetsSetsPrint;

    /** @psalm-suppress PropertyNotSetInConstructor */
    protected readonly PDO $pdo;

    public function __construct(protected readonly Connection $conn)
    {
        $this->print = $conn->print();
    }

    public function __get(string $key): Folder
    {
        $exists = false;

        foreach ($this->conn->sql() as $path) {
            assert(is_string($path));
            $exists = is_dir($path . DIRECTORY_SEPARATOR . $key);

            if ($exists) {
                break;
            }
        }

        if (!$exists) {
            throw new RuntimeException('The SQL folder does not exist: ' . $key);
        }

        return new Folder($this, $key);
    }

    public function getFetchMode(): int
    {
        return $this->conn->fetchMode;
    }

    public function getPdoDriver(): string
    {
        return $this->conn->driver;
    }

    public function getSqlDirs(): array
    {
        return $this->conn->sql();
    }

    public function connect(): static
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->pdo)) {
            return $this;
        }

        $conn = $this->conn;

        /** @psalm-suppress InaccessibleProperty */
        $this->pdo = new PDO(
            $conn->dsn,
            $conn->username,
            $conn->password,
            $conn->options,
        );

        // Always throw an exception when an error occures
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Allow getting the number of rows
        $this->pdo->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL);
        // deactivate native prepared statements by default
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        // do not alter casing of the columns from sql
        $this->pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);

        return $this;
    }

    public function quote(string $value): string
    {
        $this->connect();

        return $this->pdo->quote($value);
    }

    public function begin(): bool
    {
        $this->connect();

        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollback();
    }

    public function getConn(): PDO
    {
        $this->connect();

        return $this->pdo;
    }

    public function execute(string $query, mixed ...$args): Query
    {
        return new Query($this, $query, new Args($args));
    }
}

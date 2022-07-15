<?php

declare(strict_types=1);

namespace Conia\Puma;

use PDO;
use RuntimeException;
use Conia\Puma\DatabaseInterface;
use Conia\Puma\QueryInterface;
use Conia\Puma\Connection;

class Database implements DatabaseInterface
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    protected readonly PDO $conn;
    protected readonly string $dsn;
    protected readonly ?string $username;
    protected readonly ?string $password;
    protected readonly array $options;
    protected readonly int $fetchMode;
    protected readonly string $driver;
    protected readonly array $sqlDirs;
    protected bool $print = false;

    public function __construct(protected Connection $config)
    {
        $this->dsn = $config->dsn();
        $this->username = $config->username();
        $this->password = $config->password();
        $this->options = $config->options();
        $this->fetchMode = $config->fetchMode();
        $this->print = $config->print();
        $this->driver = $config->driver();
        $this->sqlDirs = $config->sql();
    }

    public function setPrint(bool $print): static
    {
        $this->print = $print;

        return $this;
    }

    public function shouldPrint(): bool
    {
        return $this->print;
    }

    public function getFetchMode(): int
    {
        return $this->fetchMode;
    }

    public function getPdoDriver(): string
    {
        return $this->driver;
    }

    public function getSqlDirs(): array
    {
        return $this->sqlDirs;
    }

    public function connect(): static
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (isset($this->conn)) {
            return $this;
        }

        /** @psalm-suppress InaccessibleProperty */
        $this->conn = new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options,
        );

        // Always throw an exception when an error occures
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Allow getting the number of rows
        $this->conn->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL);
        // deactivate native prepared statements by default
        $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        // do not alter casing of the columns from sql
        $this->conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);

        return $this;
    }

    public function begin(): bool
    {
        $this->connect();
        return $this->conn->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->conn->commit();
    }

    public function rollback(): bool
    {
        return $this->conn->rollback();
    }

    public function getConn(): PDO
    {
        $this->connect();
        return $this->conn;
    }

    public function execute(string $query, mixed ...$args): QueryInterface
    {
        return new Query($this, $query, new Args($args));
    }

    public function __get(string $key): Folder
    {
        $exists = false;

        foreach ($this->sqlDirs as $path) {
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
}

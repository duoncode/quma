<?php

declare(strict_types=1);

namespace Conia\Puma;

use PDO;

interface DatabaseInterface
{
    public function getFetchMode(): int;
    public function getSqlDirs(): array;
    public function getPdoDriver(): string;

    public function setPrint(bool $print): static;
    public function shouldPrint(): bool;

    // Database operations
    public function connect(): static;
    public function getConn(): PDO;
    public function begin(): bool;
    public function commit(): bool;
    public function rollback(): bool;
    public function execute(string $query, mixed ...$args): QueryInterface;
    public function __get(string $key): Folder;
}

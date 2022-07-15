<?php

declare(strict_types=1);

namespace Conia\Puma;

interface QueryInterface
{
    public function __construct(DatabaseInterface $db, string $script, Args $args);
    public function one(?int $fetchMode = null): ?array;
    public function all(?int $fetchMode = null): array;
    public function run(): bool;
    public function len(): int;
    public function interpolate(): string;
}

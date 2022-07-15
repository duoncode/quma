<?php

declare(strict_types=1);

namespace Conia\Puma;

use Conia\Puma\Connection;
use Conia\Puma\DatabaseInterface;

interface MigrationInterface
{
    public function run(DatabaseInterface $db, Connection $conn): void;
}

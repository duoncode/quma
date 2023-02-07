<?php

declare(strict_types=1);

namespace Conia\Quma\Commands;

use Conia\Quma\Commands\Environment;

/** @psalm-api */
interface MigrationInterface
{
    public function run(Environment $env): void;
}

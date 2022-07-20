<?php

declare(strict_types=1);

namespace Conia\Puma\Migrations;

use Conia\Puma\Migrations\Environment;

interface MigrationInterface
{
    public function run(Environment $env): void;
}

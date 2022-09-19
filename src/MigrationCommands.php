<?php

declare(strict_types=1);

namespace Conia\Puma;

use Conia\Cli\Commands;
use Conia\Puma\Migrations\Add;
use Conia\Puma\Migrations\CreateMigrationsTable;
use Conia\Puma\Migrations\Migrations;

class MigrationCommands
{
    public static function get(
        array|Connection $conn,
        array $options = []
    ): Commands {
        return new Commands([
            new Add($conn, $options),
            new CreateMigrationsTable($conn, $options),
            new Migrations($conn, $options),
        ]);
    }
}

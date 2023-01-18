<?php

declare(strict_types=1);

namespace Conia\Quma;

use Conia\Cli\Commands;
use Conia\Quma\Migrations\Add;
use Conia\Quma\Migrations\CreateMigrationsTable;
use Conia\Quma\Migrations\Migrations;

class MigrationCommands
{
    /** @psalm-param array<non-empty-string, Connection>|Connection $conn */
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

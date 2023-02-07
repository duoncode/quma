<?php

declare(strict_types=1);

namespace Conia\Quma;

use Conia\Cli\Commands as BaseCommands;
use Conia\Quma\Commands\Add;
use Conia\Quma\Commands\CreateMigrationsTable;
use Conia\Quma\Commands\Migrations;

/** @psalm-api */
class Commands
{
    /** @psalm-param array<non-empty-string, Connection>|Connection $conn */
    public static function get(
        array|Connection $conn,
        array $options = []
    ): BaseCommands {
        return new BaseCommands([
            new Add($conn, $options),
            new CreateMigrationsTable($conn, $options),
            new Migrations($conn, $options),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Duon\Quma;

use Duon\Cli\Commands as BaseCommands;
use Duon\Quma\Commands\Add;
use Duon\Quma\Commands\CreateMigrationsTable;
use Duon\Quma\Commands\Migrations;

/** @psalm-api */
class Commands
{
	/** @psalm-param array<non-empty-string, Connection>|Connection $conn */
	public static function get(
		array|Connection $conn,
		array $options = [],
	): BaseCommands {
		return new BaseCommands([
			new Add($conn, $options),
			new CreateMigrationsTable($conn, $options),
			new Migrations($conn, $options),
		]);
	}
}

<?php

declare(strict_types=1);

namespace FiveOrbs\Quma;

use FiveOrbs\Cli\Commands as BaseCommands;
use FiveOrbs\Quma\Commands\Add;
use FiveOrbs\Quma\Commands\CreateMigrationsTable;
use FiveOrbs\Quma\Commands\Migrations;

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

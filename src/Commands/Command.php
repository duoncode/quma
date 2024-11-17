<?php

declare(strict_types=1);

namespace FiveOrbs\Quma\Commands;

use FiveOrbs\Cli\Command as BaseCommand;
use FiveOrbs\Quma\Connection;
use FiveOrbs\Quma\Environment;

abstract class Command extends BaseCommand
{
	protected readonly Environment $env;

	/** @psalm-param array<non-empty-string, Connection>|Connection $conn */
	public function __construct(array|Connection $conn, array $options = [])
	{
		if (is_array($conn)) {
			$this->env = new Environment($conn, $options);
		} else {
			$this->env = new Environment(['default' => $conn], $options);
		}
	}
}

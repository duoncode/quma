<?php

declare(strict_types=1);

namespace Celemas\Quma\Tests\Util;

use Celemas\Quma\Connection;
use Celemas\Quma\Database;
use Celemas\Quma\Query;

final class FakeDatabase extends Database
{
	public function __construct(
		Connection $conn,
		private readonly string $driver,
		private readonly int $available,
	) {
		parent::__construct($conn);
	}

	public function getPdoDriver(): string
	{
		return $this->driver;
	}

	public function execute(string $query, mixed ...$args): Query
	{
		return new FakeQuery($this->available);
	}
}

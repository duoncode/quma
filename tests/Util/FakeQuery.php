<?php

declare(strict_types=1);

namespace Duon\Quma\Tests\Util;

use Duon\Quma\Query;

final class FakeQuery extends Query
{
	public function __construct(
		private readonly int $available,
	) {}

	public function one(?int $fetchMode = null): ?array
	{
		return ['available' => $this->available];
	}
}

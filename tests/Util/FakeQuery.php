<?php

declare(strict_types=1);

namespace Duon\Quma\Tests\Util;

use Closure;
use Duon\Quma\Query;

final class FakeQuery extends Query
{
	public function __construct(
		private readonly int $available,
	) {}

	public function one(string|Closure|null $map = null, ?int $fetchMode = null): array|object|null
	{
		return ['available' => $this->available];
	}
}

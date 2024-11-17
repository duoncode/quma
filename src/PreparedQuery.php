<?php

declare(strict_types=1);

namespace FiveOrbs\Quma;

class PreparedQuery
{
	/** @psalm-param array<non-empty-string, non-empty-string> $swaps */
	public function __construct(
		public readonly string $query,
		public readonly array $swaps,
	) {}
}

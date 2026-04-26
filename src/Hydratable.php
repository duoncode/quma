<?php

declare(strict_types=1);

namespace Duon\Quma;

/** @psalm-api */
interface Hydratable
{
	/** @param array<string, mixed> $row */
	public static function fromRow(array $row): static;
}

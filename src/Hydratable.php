<?php

declare(strict_types=1);

namespace Celemas\Quma;

/** @api */
interface Hydratable
{
	/** @param array<string, mixed> $row */
	public static function fromRow(array $row): static;
}

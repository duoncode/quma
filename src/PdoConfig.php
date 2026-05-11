<?php

declare(strict_types=1);

namespace Celemas\Quma;

use PDO;

/** @internal */
final class PdoConfig
{
	public ?string $username = null;
	public ?string $password = null;

	/** @var array<array-key, mixed> */
	public array $options = [];

	public int $fetchMode = PDO::FETCH_ASSOC;
}

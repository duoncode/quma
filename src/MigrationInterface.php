<?php

declare(strict_types=1);

namespace Duon\Quma;

/** @psalm-api */
interface MigrationInterface
{
	public function run(Environment $env): void;
}

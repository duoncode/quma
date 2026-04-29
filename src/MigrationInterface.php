<?php

declare(strict_types=1);

namespace Duon\Quma;

/** @api */
interface MigrationInterface
{
	public function run(Environment $env): void;
}

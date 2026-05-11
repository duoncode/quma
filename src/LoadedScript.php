<?php

declare(strict_types=1);

namespace Celemas\Quma;

final class LoadedScript
{
	public function __construct(
		public readonly string $source,
		public readonly string $sourcePath,
		public readonly ?string $cachePath = null,
	) {}
}

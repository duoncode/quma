<?php

declare(strict_types=1);

namespace Duon\Quma\Hydration;

/** @internal */
interface MetadataCache
{
	/** @psalm-param class-string $class */
	public function metadata(string $class): ClassMetadata;
}

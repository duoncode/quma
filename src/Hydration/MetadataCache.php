<?php

declare(strict_types=1);

namespace Celemas\Quma\Hydration;

/** @internal */
interface MetadataCache
{
	/** @param class-string $class */
	public function metadata(string $class): ClassMetadata;
}

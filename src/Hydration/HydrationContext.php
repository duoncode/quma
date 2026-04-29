<?php

declare(strict_types=1);

namespace Duon\Quma\Hydration;

/** @internal */
final readonly class HydrationContext
{
	/**
	 * @psalm-param class-string $class
	 * @psalm-param non-empty-string $parameter
	 * @psalm-param non-empty-string $column
	 * @param list<string> $rowKeys
	 */
	public function __construct(
		public string $class,
		public string $parameter,
		public string $column,
		public ?string $sourcePath,
		public array $rowKeys,
	) {}
}

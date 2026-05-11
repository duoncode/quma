<?php

declare(strict_types=1);

namespace Celemas\Quma;

/** @api */
final class MissingColumnException extends HydrationException
{
	/**
	 * @param class-string $class
	 * @param non-empty-string $parameter
	 * @param non-empty-string $column
	 * @param list<string> $rowKeys
	 */
	public static function forColumn(
		string $class,
		string $parameter,
		string $column,
		?string $sourcePath,
		array $rowKeys,
	): self {
		return new self(self::message(
			$class,
			$sourcePath,
			"missing required column '{$column}' for parameter '\${$parameter}'. Row keys: "
			. self::formatRowKeys($rowKeys)
			. '.',
		));
	}
}

<?php

declare(strict_types=1);

namespace Duon\Quma;

use InvalidArgumentException;

/** @api */
final class Delimiters
{
	public const string DEFAULT_OPEN = '[::';
	public const string DEFAULT_CLOSE = '::]';

	public function __construct(
		public readonly string $open = self::DEFAULT_OPEN,
		public readonly string $close = self::DEFAULT_CLOSE,
	) {
		$this->validate('opening', $this->open);
		$this->validate('closing', $this->close);
	}

	/** @return array{open: string, close: string} */
	public function values(): array
	{
		return [
			'open' => $this->open,
			'close' => $this->close,
		];
	}

	public function token(string $name): string
	{
		return $this->open . $name . $this->close;
	}

	private function validate(string $label, string $delimiter): void
	{
		if ($delimiter === '') {
			throw new InvalidArgumentException("Static placeholder {$label} delimiter must not be empty.");
		}

		if (str_contains($delimiter, "\0")) {
			throw new InvalidArgumentException(
				"Static placeholder {$label} delimiter must not contain NUL bytes.",
			);
		}
	}
}

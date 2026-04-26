<?php

declare(strict_types=1);

namespace Duon\Quma;

use InvalidArgumentException;
use RuntimeException;

final class StaticPlaceholders
{
	public const string NAME_PATTERN = '[A-Za-z_][A-Za-z0-9_.:-]*';

	private const string TOKEN_PATTERN = '/\[::(' . self::NAME_PATTERN . ')::\]/';
	private const string TOKEN_START_PATTERN = '/^\[::(' . self::NAME_PATTERN . ')::\]/';

	/** @var array<string, string> */
	private readonly array $values;

	/** @param array<array-key, mixed> $config */
	public function __construct(
		private readonly string $driver,
		array $config,
	) {
		$config = $this->normalizeConfig($config);
		$this->values = array_replace(
			$config['all'] ?? [],
			$config[$this->driver] ?? [],
		);
	}

	/** @return array<string, string> */
	public function values(): array
	{
		return $this->values;
	}

	public function compile(string $source, string $path, bool $isTemplate): string
	{
		if ($isTemplate) {
			return $this->compileTemplate($source, $path);
		}

		return $this->compileSql($source, $path);
	}

	public function compileSql(string $source, string $path): string
	{
		return $this->substituteFragment($source, $path, $source, 0);
	}

	public function compileTemplate(string $source, string $path): string
	{
		$tokens = token_get_all($source);
		$compiled = '';
		$offset = 0;

		foreach ($tokens as $token) {
			$text = is_array($token) ? $token[1] : $token;

			if (is_array($token) && $token[0] === T_INLINE_HTML) {
				$compiled .= $this->substituteFragment($text, $path, $source, $offset);
			} else {
				$compiled .= $text;
			}

			$offset += strlen($text);
		}

		return $compiled;
	}

	public function assertNoTemplatePlaceholders(string $source, string $path): void
	{
		$offset = strpos($source, '[::');

		if ($offset === false) {
			return;
		}

		$token = '[::';
		$matches = [];

		if (preg_match(self::TOKEN_START_PATTERN, substr($source, $offset), $matches) === 1) {
			$token = $matches[0];
		}

		[$line, $column] = $this->location($source, $offset);

		throw new RuntimeException(
			"Static placeholder {$token} remained after rendering template {$path}:{$line}:{$column}. "
			. 'Placeholders inside PHP blocks or generated template output are not supported. '
			. 'Move static placeholders into the literal SQL portion of the .tpql file.',
		);
	}

	/**
	 * @param array<array-key, mixed> $config
	 *
	 * @return array<string, array<string, string>>
	 */
	private function normalizeConfig(array $config): array
	{
		$normalized = [];

		foreach ($config as $scope => $values) {
			if (!is_string($scope) || $scope === '') {
				throw new InvalidArgumentException(
					'Static placeholder scopes must be non-empty strings.',
				);
			}

			if ($scope === 'default') {
				throw new InvalidArgumentException(
					"Static placeholders use the shared scope 'all'. Replace placeholders['default'] with placeholders['all'].",
				);
			}

			if (!is_array($values)) {
				throw new InvalidArgumentException(
					"Static placeholders for scope '{$scope}' must be an array of string values.",
				);
			}

			$normalized[$scope] = $this->normalizeValues($scope, $values);
		}

		return $normalized;
	}

	/**
	 * @param array<array-key, mixed> $values
	 *
	 * @return array<string, string>
	 */
	private function normalizeValues(string $scope, array $values): array
	{
		$normalized = [];

		foreach ($values as $name => $value) {
			if (!is_string($name) || !preg_match('/^' . self::NAME_PATTERN . '$/', $name)) {
				throw new InvalidArgumentException(
					"Invalid static placeholder name in scope '{$scope}'. Names must match "
					. self::NAME_PATTERN
					. '.',
				);
			}

			if (!is_string($value)) {
				throw new InvalidArgumentException(
					"Static placeholder '{$name}' in scope '{$scope}' must be a string.",
				);
			}

			if (str_contains($value, '[::')) {
				throw new InvalidArgumentException(
					"Static placeholder '{$name}' in scope '{$scope}' must not contain another static placeholder.",
				);
			}

			$normalized[$name] = $value;
		}

		return $normalized;
	}

	private function substituteFragment(
		string $fragment,
		string $path,
		string $source,
		int $baseOffset,
	): string {
		$this->assertNoMalformedTokens($fragment, $path, $source, $baseOffset);

		$matches = [];
		$result = preg_match_all(
			self::TOKEN_PATTERN,
			$fragment,
			$matches,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
		);

		if ($result === false || $result === 0) {
			return $fragment;
		}

		$compiled = '';
		$cursor = 0;

		foreach ($matches as $match) {
			$token = $match[0][0];
			$offset = $match[0][1];
			$name = $match[1][0];

			$compiled .= substr($fragment, $cursor, $offset - $cursor);

			if (!array_key_exists($name, $this->values)) {
				throw $this->unknownPlaceholder($token, $name, $path, $source, $baseOffset + $offset);
			}

			$compiled .= $this->values[$name];
			$cursor = $offset + strlen($token);
		}

		return $compiled . substr($fragment, $cursor);
	}

	private function assertNoMalformedTokens(
		string $fragment,
		string $path,
		string $source,
		int $baseOffset,
	): void {
		$offset = 0;

		while (($position = strpos($fragment, '[::', $offset)) !== false) {
			$matches = [];
			$tail = substr($fragment, $position);

			if (preg_match(self::TOKEN_START_PATTERN, $tail, $matches) !== 1) {
				throw $this->malformedPlaceholder($path, $source, $baseOffset + $position);
			}

			$offset = $position + strlen($matches[0]);
		}
	}

	private function unknownPlaceholder(
		string $token,
		string $name,
		string $path,
		string $source,
		int $offset,
	): RuntimeException {
		[$line, $column] = $this->location($source, $offset);

		return new RuntimeException(
			"Unknown static placeholder {$token} in {$path}:{$line}:{$column} for driver \"{$this->driver}\".\n"
			. "No value was configured for \"{$name}\".\n"
			. "Add placeholders['all']['{$name}'] or placeholders['{$this->driver}']['{$name}'].\n"
			. 'Static placeholders are raw SQL fragments. Use them only for trusted configuration, never for user input.',
		);
	}

	private function malformedPlaceholder(string $path, string $source, int $offset): RuntimeException
	{
		[$line, $column] = $this->location($source, $offset);

		return new RuntimeException(
			"Malformed static placeholder in {$path}:{$line}:{$column}.\n"
			. 'Expected [::name::] where name matches: '
			. self::NAME_PATTERN
			. ".\n"
			. 'Examples: [::prefix::], [::schema.name::], [::tenant-prefix::], [::cms:prefix::].',
		);
	}

	/** @return array{0: int, 1: int} */
	private function location(string $source, int $offset): array
	{
		$before = substr($source, 0, $offset);
		$line = substr_count($before, "\n") + 1;
		$lineStart = strrpos($before, "\n");
		$column = $offset + 1;

		if ($lineStart !== false) {
			$column = $offset - $lineStart;
		}

		return [$line, $column];
	}
}

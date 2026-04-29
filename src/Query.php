<?php

declare(strict_types=1);

namespace Duon\Quma;

use Closure;
use Duon\Quma\Hydration\Hydrator;
use Generator;
use InvalidArgumentException;
use PDO;
use PDOStatement;

/** @api */
class Query
{
	// Matches multi line single and double quotes and handles \' \" escapes
	public const string PATTERN_STRING = '/([\'"])(?:\\\1|[\s\S])*?\1/';

	// PostgreSQL blocks delimited with $$
	public const string PATTERN_BLOCK = '/(\$\$)[\s\S]*?\1/';

	// Multi line comments /* */
	public const string PATTERN_COMMENT_MULTI = '/\/\*([\s\S]*?)\*\//';

	// Single line comments --
	public const string PATTERN_COMMENT_SINGLE = '/--.*$/m';

	protected PDOStatement $stmt;
	protected bool $executed = false;
	protected ?Hydrator $hydrator = null;

	public function __construct(
		protected Database $db,
		protected string $query,
		protected Args $args,
		protected ?string $sourcePath = null,
	) {
		$this->stmt = $this->db->getConn()->prepare($query);

		if ($args->count() > 0) {
			$this->bindArgs($args->get(), $args->type());
		}

		if ($db->print()) {
			$msg =
				"\n\n-----------------------------------------------\n\n"
				. $this->interpolate()
				. "\n------------------------------------------------\n";

			if (($_SERVER['SERVER_SOFTWARE'] ?? null) !== null) {
				// @codeCoverageIgnoreStart
				error_log($msg);

				// @codeCoverageIgnoreEnd
			} else {
				echo $msg;
			}
		}
	}

	public function __toString(): string
	{
		return $this->interpolate();
	}

	/**
	 * @template T of object
	 *
	 * @param class-string<T>|Closure(array<string, mixed>):class-string<T>|null $map
	 * @return ($map is null ? array<string, mixed>|null : T|null)
	 */
	public function one(string|Closure|null $map = null, ?int $fetchMode = null): array|object|null
	{
		[$map, $fetchMode] = $this->terminalOptions($map, $fetchMode);

		$this->db->connect();

		if (!$this->executed) {
			$this->stmt->execute();
			$this->executed = true;
		}

		/**
		 * @mago-expect lint:inline-variable-return Psalm makes this necessary
		 * @var array<string, mixed>|null $record
		 */
		$record = $this->fetchArrayRecord($fetchMode);

		if ($record === null || $map === null) {
			return $record;
		}

		/** @var T $object */
		$object = $this->hydrator()->hydrate($record, $map, $this->sourcePath);

		return $object;
	}

	/**
	 * @template T of object
	 *
	 * @param class-string<T>|Closure(array<string, mixed>):class-string<T>|null $map
	 * @return ($map is null ? list<array<string, mixed>> : list<T>)
	 */
	public function all(string|Closure|null $map = null, ?int $fetchMode = null): array
	{
		[$map, $fetchMode] = $this->terminalOptions($map, $fetchMode);

		$this->db->connect();
		$this->stmt->execute();

		if ($map === null) {
			/**
			 * @mago-expect lint:inline-variable-return Psalm makes this necessary
			 * @var list<array<string, mixed>> $records
			 */
			$records = $this->stmt->fetchAll($fetchMode);

			return $records;
		}

		/** @var list<T> $result */
		$result = [];
		$records = $this->stmt->fetchAll($fetchMode);

		/** @var list<array<array-key, mixed>> $records */
		foreach ($records as $record) {
			/** @var T $object */
			$object = $this->hydrator()->hydrate($record, $map, $this->sourcePath);
			$result[] = $object;
		}

		return $result;
	}

	/**
	 * @template T of object
	 *
	 * @param class-string<T>|Closure(array<string, mixed>):class-string<T>|null $map
	 * @return ($map is null
	 *     ? Generator<int, array<string, mixed>, mixed, void>
	 *     : Generator<int, T, mixed, void>)
	 */
	public function lazy(string|Closure|null $map = null, ?int $fetchMode = null): Generator
	{
		[$map, $fetchMode] = $this->terminalOptions($map, $fetchMode);

		$this->db->connect();
		$this->stmt->execute();

		while (($record = $this->fetchArrayRecord($fetchMode)) !== null) {
			/** @var array<string, mixed> $record */

			if ($map === null) {
				yield $record;

				continue;
			}

			$object = $this->hydrator()->hydrate($record, $map, $this->sourcePath);
			/** @var T $object */

			yield $object;
		}
	}

	/**
	 * @return array{0: string|Closure|null, 1: int}
	 */
	private function terminalOptions(string|Closure|null $map, ?int $fetchMode): array
	{
		$mode = $fetchMode ?? ($map === null ? $this->db->getFetchMode() : PDO::FETCH_ASSOC);

		if ($map !== null && $mode !== PDO::FETCH_ASSOC) {
			throw new InvalidArgumentException('Hydration requires PDO::FETCH_ASSOC.');
		}

		return [$map, $mode];
	}

	private function hydrator(): Hydrator
	{
		return $this->hydrator ??= Hydrator::default();
	}

	public function run(): bool
	{
		$this->db->connect();

		return $this->stmt->execute();
	}

	public function len(): int
	{
		$this->db->connect();
		$this->stmt->execute();

		return $this->stmt->rowCount();
	}

	/**
	 * For debugging purposes only.
	 *
	 * Replaces any parameter placeholders in a query with the
	 * value of that parameter and returns the query as string.
	 *
	 * Covers most of the cases but is not perfect.
	 */
	public function interpolate(): string
	{
		$prep = $this->prepareQuery($this->query);
		$argsArray = $this->args->get();

		if ($this->args->type() === ArgType::Named) {
			$interpolated = $this->interpolateNamed($prep->query, $this->args->getNamed());
		} else {
			$interpolated = $this->interpolatePositional($prep->query, $argsArray);
		}

		return $this->restoreQuery($interpolated, $prep);
	}

	protected function bindArgs(array $args, ArgType $argType): void
	{
		array_walk(
			$args,
			function (mixed $value, int|string $index) use ($argType): void {
				if ($argType === ArgType::Named) {
					$arg = ':' . $index;
				} else {
					$arg = (int) $index + 1; // question mark placeholders are 1-indexed
				}

				$this->bindValue($arg, $value);
			},
		);
	}

	protected function bindValue(string|int $arg, mixed $value): void
	{
		switch (gettype($value)) {
			case 'boolean':
				$this->stmt->bindValue($arg, $value, PDO::PARAM_BOOL);

				break;

			case 'integer':
				$this->stmt->bindValue($arg, $value, PDO::PARAM_INT);

				break;

			case 'string':
				$this->stmt->bindValue($arg, $value, PDO::PARAM_STR);

				break;

			case 'NULL':
				$this->stmt->bindValue($arg, $value, PDO::PARAM_NULL);

				break;

			case 'array':
				$this->stmt->bindValue($arg, json_encode($value), PDO::PARAM_STR);

				break;

			default:
				throw new InvalidArgumentException(
					'Only the types bool, int, string, null and array are supported',
				);
		}
	}

	protected function fetchArrayRecord(int $fetchMode): ?array
	{
		return $this->nullIfNot($this->stmt->fetch($fetchMode));
	}

	protected function nullIfNot(mixed $value): ?array
	{
		if (is_array($value)) {
			return $value;
		}

		return null;
	}

	protected function convertValue(mixed $value): string
	{
		if (is_string($value)) {
			return "'" . $value . "'";
		}

		if (is_array($value)) {
			$encoded = json_encode($value);

			return "'" . ($encoded !== false ? $encoded : '[]') . "'";
		}

		if (is_null($value)) {
			return 'NULL';
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		return (string) $value;
	}

	protected function prepareQuery(string $query): PreparedQuery
	{
		$patterns = [
			self::PATTERN_BLOCK,
			self::PATTERN_STRING,
			self::PATTERN_COMMENT_MULTI,
			self::PATTERN_COMMENT_SINGLE,
		];

		$swaps = [];

		$i = 0;

		do {
			$found = false;

			foreach ($patterns as $pattern) {
				$matches = [];

				if ($query !== null && preg_match($pattern, $query, $matches)) {
					$match = $matches[0];
					$replacement = "___CHUCK_REPLACE_{$i}___";
					assert($match !== '', 'Query placeholder match must not be empty.');
					$swaps[$replacement] = $match;

					$query = preg_replace($pattern, $replacement, $query, limit: 1);
					$found = true;
					$i++;

					break;
				}
			}
		} while ($found);

		return new PreparedQuery($query ?? '', $swaps);
	}

	protected function restoreQuery(string $query, PreparedQuery $prep): string
	{
		foreach ($prep->swaps as $swap => $replacement) {
			$query = str_replace($swap, $replacement, $query);
		}

		return $query;
	}

	/** @param array<array-key, mixed> $args */
	protected function interpolateNamed(string $query, array $args): string
	{
		$map = [];

		array_walk(
			$args,
			function (mixed $value, int|string $key) use (&$map): void {
				if (is_string($key) && $key !== '') {
					$map[':' . $key] = $this->convertValue($value);
				}
			},
		);

		return strtr($query, $map);
	}

	protected function interpolatePositional(string $query, array $args): string
	{
		$result = $query;

		array_walk(
			$args,
			function (mixed $value) use (&$result): void {
				$replaced = preg_replace('/\\?/', $this->convertValue($value), $result, 1);
				$result = $replaced ?? $result;
			},
		);

		return $result;
	}
}

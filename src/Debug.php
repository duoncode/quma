<?php

declare(strict_types=1);

namespace Duon\Quma;

use RuntimeException;

/** @internal */
final class Debug
{
	public const string ENV_PRINT = 'QUMA_DEBUG_PRINT';
	public const string ENV_TRANSLATED = 'QUMA_DEBUG_TRANSLATED';
	public const string ENV_INTERPOLATED = 'QUMA_DEBUG_INTERPOLATED';

	private static int $counter = 0;

	public static function query(Database $db, string $query, Args $args, ?string $sourcePath): void
	{
		$print = self::prints();
		$writeInterpolated = self::writesInterpolated();

		if (!$print && !$writeInterpolated) {
			return;
		}

		$interpolated = self::interpolate($query, $args);

		if ($print) {
			self::printQuery($interpolated);
		}

		if ($writeInterpolated) {
			self::writeInterpolated(
				$db->getPdoDriver(),
				$sourcePath,
				$db->getSqlDirs(),
				$interpolated,
			);
		}
	}

	public static function interpolate(string $query, Args $args): string
	{
		$prep = self::prepareQuery($query);

		if ($args->type() === ArgType::Named) {
			$interpolated = self::interpolateNamed($prep->query, $args->getNamed());
		} else {
			$interpolated = self::interpolatePositional($prep->query, $args->get());
		}

		return self::restoreQuery($interpolated, $prep);
	}

	public static function prints(): bool
	{
		$value = self::env(self::ENV_PRINT);

		if ($value === null) {
			return false;
		}

		return !in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
	}

	public static function writesInterpolated(): bool
	{
		return self::env(self::ENV_INTERPOLATED) !== null;
	}

	/** @param array<array-key, mixed> $roots */
	public static function writeTranslatedQuery(
		string $driver,
		string $sourcePath,
		array $roots,
		string $source,
	): void {
		$dir = self::dir(self::ENV_TRANSLATED);

		if ($dir === null) {
			return;
		}

		self::write(
			$dir,
			$driver,
			'translated',
			'queries' . DIRECTORY_SEPARATOR . self::relativeToRoots($sourcePath, $roots),
			$source,
		);
	}

	public static function writeTranslatedMigration(
		string $driver,
		string $namespace,
		string $sourcePath,
		string $source,
	): void {
		$dir = self::dir(self::ENV_TRANSLATED);

		if ($dir === null) {
			return;
		}

		self::write(
			$dir,
			$driver,
			'translated',
			'migrations' . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . basename($sourcePath),
			$source,
		);
	}

	/** @param array<array-key, mixed> $roots */
	public static function writeInterpolated(
		string $driver,
		?string $sourcePath,
		array $roots,
		string $source,
	): void {
		$dir = self::dir(self::ENV_INTERPOLATED);

		if ($dir === null) {
			return;
		}

		self::write(
			$dir,
			$driver,
			'interpolated',
			self::interpolatedPath($sourcePath, $roots, $source),
			$source,
		);
	}

	private static function printQuery(string $query): void
	{
		$msg =
			"\n\n-----------------------------------------------\n\n"
			. $query
			. "\n------------------------------------------------\n";

		if (($_SERVER['SERVER_SOFTWARE'] ?? null) !== null) {
			// @codeCoverageIgnoreStart
			error_log($msg);

			// @codeCoverageIgnoreEnd
		} else {
			echo $msg;
		}
	}

	private static function value(mixed $value): string
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

	private static function prepareQuery(string $query): PreparedQuery
	{
		$patterns = [
			Query::PATTERN_BLOCK,
			Query::PATTERN_STRING,
			Query::PATTERN_COMMENT_MULTI,
			Query::PATTERN_COMMENT_SINGLE,
		];

		$swaps = [];
		$i = 0;

		do {
			$found = false;

			foreach ($patterns as $pattern) {
				$matches = [];

				if (preg_match($pattern, $query, $matches) === 1) {
					$match = $matches[0];
					$replacement = "___CHUCK_REPLACE_{$i}___";
					assert($match !== '', 'Query placeholder match must not be empty.');
					$swaps[$replacement] = $match;

					$query = preg_replace($pattern, $replacement, $query, limit: 1) ?? $query;
					$found = true;
					$i++;

					break;
				}
			}
		} while ($found);

		return new PreparedQuery($query, $swaps);
	}

	private static function restoreQuery(string $query, PreparedQuery $prep): string
	{
		foreach ($prep->swaps as $swap => $replacement) {
			$query = str_replace($swap, $replacement, $query);
		}

		return $query;
	}

	/** @param array<array-key, mixed> $args */
	private static function interpolateNamed(string $query, array $args): string
	{
		$map = [];

		array_walk(
			$args,
			static function (mixed $value, int|string $key) use (&$map): void {
				if (is_string($key) && $key !== '') {
					$map[':' . $key] = self::value($value);
				}
			},
		);

		return strtr($query, $map);
	}

	/** @param array<array-key, mixed> $args */
	private static function interpolatePositional(string $query, array $args): string
	{
		$result = $query;

		array_walk(
			$args,
			static function (mixed $value) use (&$result): void {
				$replaced = preg_replace('/\\?/', self::value($value), $result, 1);
				$result = $replaced ?? $result;
			},
		);

		return $result;
	}

	private static function env(string $name): ?string
	{
		$value = getenv($name);

		if ($value === false) {
			$value = $_SERVER[$name] ?? $_ENV[$name] ?? null;
		}

		if (!is_string($value)) {
			return null;
		}

		$value = trim($value);

		return $value === '' ? null : $value;
	}

	/** @return non-empty-string|null */
	private static function dir(string $name): ?string
	{
		$dir = self::env($name);

		if ($dir === null) {
			return null;
		}

		if (!is_dir($dir)) {
			throw new RuntimeException("Quma debug directory does not exist for {$name}: {$dir}");
		}

		if (!is_writable($dir)) {
			throw new RuntimeException("Quma debug directory is not writable for {$name}: {$dir}");
		}

		$path = realpath($dir);

		if ($path === false || $path === '') {
			throw new RuntimeException("Quma debug directory does not exist for {$name}: {$dir}");
		}

		return $path;
	}

	private static function write(
		string $dir,
		string $driver,
		string $group,
		string $relative,
		string $source,
	): void {
		$path =
			$dir
			. DIRECTORY_SEPARATOR
			. self::safeSegment($driver)
			. DIRECTORY_SEPARATOR
			. $group
			. DIRECTORY_SEPARATOR
			. self::safeRelativePath($relative);
		$targetDir = dirname($path);

		if (!is_dir($targetDir) && !mkdir($targetDir, 0o775, true) && !is_dir($targetDir)) {
			throw new RuntimeException('Could not create Quma debug directory: ' . $targetDir);
		}

		if (file_put_contents($path, $source, LOCK_EX) === false) {
			throw new RuntimeException('Could not write Quma debug file: ' . $path);
		}
	}

	/** @param array<array-key, mixed> $roots */
	private static function relativeToRoots(string $sourcePath, array $roots): string
	{
		$realSource = realpath($sourcePath);
		$source = $realSource !== false ? $realSource : $sourcePath;

		foreach ($roots as $root) {
			if (!is_string($root) || $root === '') {
				continue;
			}

			$realRoot = realpath($root);
			$root = $realRoot !== false ? $realRoot : $root;
			$prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if (str_starts_with($source, $prefix)) {
				$relative = substr($source, strlen($prefix));

				return $relative === '' ? basename($sourcePath) : $relative;
			}
		}

		return basename($sourcePath);
	}

	/** @param array<array-key, mixed> $roots */
	private static function interpolatedPath(?string $sourcePath, array $roots, string $source): string
	{
		$suffix = self::suffix($source);

		if ($sourcePath === null) {
			return 'execute' . DIRECTORY_SEPARATOR . $suffix . '.sql';
		}

		$relative = self::relativeToRoots($sourcePath, $roots);
		$dir = dirname($relative);
		$name = pathinfo($relative, PATHINFO_FILENAME);
		$name = $name !== '' ? $name : 'query';
		$file = $name . '-' . $suffix . '.sql';

		return $dir === '.' ? $file : $dir . DIRECTORY_SEPARATOR . $file;
	}

	private static function suffix(string $source): string
	{
		self::$counter++;
		$pid = getmypid();
		$pid = is_int($pid) ? (string) $pid : '0';

		return (
			date('Ymd-His')
			. '-'
			. $pid
			. '-'
			. self::$counter
			. '-'
			. substr(hash('xxh128', $source), 0, 12)
		);
	}

	private static function safeRelativePath(string $path): string
	{
		$parts = preg_split('/[\\/]+/', $path, -1, PREG_SPLIT_NO_EMPTY);

		if (!is_array($parts) || count($parts) === 0) {
			return 'query.sql';
		}

		$result = [];

		foreach ($parts as $part) {
			if ($part === '.' || $part === '..') {
				continue;
			}

			$result[] = self::safeSegment($part);
		}

		$path = implode(DIRECTORY_SEPARATOR, $result);

		return $path !== '' ? $path : 'query.sql';
	}

	private static function safeSegment(string $segment): string
	{
		$result = preg_replace('/[^A-Za-z0-9._:-]+/', '_', $segment);
		$result = trim($result ?? '', '.');

		return $result === '' ? '_' : $result;
	}
}

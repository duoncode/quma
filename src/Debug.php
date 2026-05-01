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

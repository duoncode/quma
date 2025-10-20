<?php

declare(strict_types=1);

namespace Duon\Quma;

use Duon\Cli\Opts;
use Duon\Quma\Connection;
use Duon\Quma\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * @psalm-api
 *
 * @psalm-import-type MigrationDirs from \Duon\Quma\Connection
 */
class Environment
{
	public readonly Connection $conn;
	public readonly string $driver;
	public readonly bool $showStacktrace;
	public readonly string $table;
	public readonly string $columnMigration;
	public readonly string $columnApplied;
	public readonly Database $db;

	/** @psalm-param array<non-empty-string, Connection> $connections */
	public function __construct(
		array $connections,
		public readonly array $options,
	) {
		$opts = new Opts();

		try {
			$key = $opts->get('--conn', 'default');
			assert(isset($connections[$key]));
			$this->conn = $connections[$key];
		} catch (Throwable) {
			$key = $key ?? '<undefied>';

			throw new RuntimeException("Connection '{$key}' does not exist");
		}

		$this->showStacktrace = $opts->has('--stacktrace');
		$this->db = new Database($this->conn);
		$this->driver = $this->conn->driver;
		$this->table = $this->conn->migrationsTable();
		$this->columnMigration = $this->conn->migrationsColumnMigration();
		$this->columnApplied = $this->conn->migrationsColumnApplied();
	}

	public function getMigrations(): array|false
	{
		/** @psalm-var MigrationDirs */
		$migrations = [];
		$migrationDirs = $this->conn->migrations();

		if (count($migrationDirs) === 0) {
			echo "\033[1;31mNotice\033[0m: No migration directories defined in configuration\033[0m\n";

			return false;
		}

		if (array_is_list($migrationDirs)) {
			$migrations['default'] = $this->collectMigrations($migrationDirs);

			return $migrations;
		}

		foreach ($migrationDirs as $namespace => $namespaceDirs) {
			$migrations[$namespace] = $this->collectMigrations($namespaceDirs);
		}

		return $migrations;
	}

	protected function collectMigrations(array $migrationDirs): array
	{
		$migrations = [];

		foreach ($migrationDirs as $path) {
			$migrations = array_merge(
				$migrations,
				array_filter(glob("{$path}/*.php"), 'is_file'),
				array_filter(glob("{$path}/*.sql"), 'is_file'),
				array_filter(glob("{$path}/*.tpql"), 'is_file'),
			);
		}

		// Sort by file name instead of full path
		uasort($migrations, function ($a, $b) {
			$a = is_string($a) ? $a : '';
			$b = is_string($b) ? $b : '';

			return (basename($a) < basename($b)) ? -1 : 1;
		});

		return $migrations;
	}

	public function checkIfMigrationsTableExists(Database $db): bool
	{
		$driver = $db->getPdoDriver();
		$table = $this->table;

		if ($driver === 'pgsql' && strpos($table, '.') !== false) {
			[$schema, $table] = explode('.', $table);
		} else {
			$schema = 'public';
		}

		$query = match ($driver) {
			'sqlite' => "
                SELECT count(*) AS available
                FROM sqlite_master
                WHERE type='table'
                AND name='{$table}';",

			'mysql' => "
                SELECT count(*) AS available
                FROM information_schema.tables
                WHERE table_name='{$table}';",

			'pgsql' => "
                SELECT count(*) AS available
                FROM pg_tables
                WHERE schemaname = '{$schema}'
                AND tablename = '{$table}';",
		};

		if ($query && ($db->execute($query)->one(PDO::FETCH_ASSOC)['available'] ?? 0) === 1) {
			return true;
		}

		return false;
	}

	public function getMigrationsTableDDL(): string|false
	{
		if ($this->driver === 'pgsql' && strpos($this->table, '.') !== false) {
			[$schema, $table] = explode('.', $this->table);
		} else {
			$schema = 'public';
			$table = $this->table;
		}
		$columnMigration = $this->columnMigration;
		$columnApplied = $this->columnApplied;

		switch ($this->driver) {
			case 'sqlite':
				return "CREATE TABLE {$table} (
    {$columnMigration} text NOT NULL,
    {$columnApplied} text DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ({$columnMigration}),
    CHECK(typeof(\"{$columnMigration}\") = \"text\" AND length(\"{$columnMigration}\") <= 256),
    CHECK(typeof(\"{$columnApplied}\") = \"text\" AND length(\"{$columnApplied}\") = 19)
);";

			case 'pgsql':
				return "CREATE TABLE {$schema}.{$table} (
    {$columnMigration} text NOT NULL CHECK (char_length({$columnMigration}) <= 256),
    {$columnApplied} timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT pk_{$table} PRIMARY KEY ({$columnMigration})
);";

			case 'mysql':
				return "CREATE TABLE {$table} (
    {$columnMigration} varchar(256) NOT NULL,
    {$columnApplied} timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ({$columnMigration})
);";

			default:
				// Cannot be reliably tested.
				// Would require an unsupported driver to be installed.
				// @codeCoverageIgnoreStart
				return false;
				// @codeCoverageIgnoreEnd
		}
	}
}

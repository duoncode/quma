<?php

declare(strict_types=1);

namespace Duon\Quma\Commands;

use Duon\Cli\Command;
use Duon\Cli\Opts;
use Duon\Quma\Connection;
use Duon\Quma\Database;
use Duon\Quma\Environment;
use Duon\Quma\MigrationInterface;
use Override;
use PDOException;
use RuntimeException;
use Throwable;

final class Migrations extends Command
{
	protected const string STARTED = 'start';
	protected const string ERROR = 'error';
	protected const string WARNING = 'warning';
	protected const string SUCCESS = 'success';

	protected readonly Environment $env;
	protected string $name = 'migrations';
	protected string $group = 'Database';
	protected string $prefix = 'db';
	protected string $description = 'Apply missing database migrations';

	/** @psalm-param array<non-empty-string, Connection>|Connection $conn */
	public function __construct(array|Connection $conn, array $options = [])
	{
		if (is_array($conn)) {
			$this->env = new Environment($conn, $options);
		} else {
			$this->env = new Environment(['default' => $conn], $options);
		}
	}

	#[Override]
	public function run(): string|int
	{
		$env = $this->env;
		$opts = new Opts();
		$namespace = $opts->get('--namespace', '');
		$showStacktrace = $opts->has('--stacktrace');
		$apply = $opts->has('--apply');
		$driverSupported = in_array($env->driver, ['sqlite', 'mysql', 'pgsql'], strict: true);
		$tableExists = $driverSupported && $env->checkIfMigrationsTableExists($env->db);

		if (!$apply && $env->driver === 'mysql') {
			return $this->planMigrations($namespace, $tableExists);
		}

		if ($driverSupported && !$tableExists && !$this->supportsTransactions()) {
			$result = $this->createMigrationsTable();

			if ($result !== 0) {
				return $result;
			}

			$tableExists = true;
		}

		return $this->migrate(
			$env->db,
			$env->conn,
			$namespace,
			$showStacktrace,
			$apply,
			$tableExists,
		);
	}

	protected function migrate(
		Database $db,
		Connection $conn,
		string $namespace,
		bool $showStacktrace,
		bool $apply,
		bool $tableExists,
	): int {
		$migrations = $this->migrationsForNamespace($namespace);

		if ($migrations === false) {
			return 1;
		}

		return $this->runMigrations(
			$namespace ?: 'default',
			$migrations,
			$db,
			$conn,
			$showStacktrace,
			$apply,
			$tableExists,
		);
	}

	/**
	 * @return list<string>|false
	 */
	protected function migrationsForNamespace(string $namespace): array|false
	{
		$migrationNamespaces = $this->env->getMigrations();

		if ($migrationNamespaces === false) {
			return false;
		}

		if ($namespace) {
			if (!array_key_exists($namespace, $migrationNamespaces)) {
				$this->error("Migration namespace '{$namespace}' does not exist");

				return false;
			}

			return $migrationNamespaces[$namespace];
		}

		if (!array_key_exists('default', $migrationNamespaces)) {
			$this->error("Migration namespace 'default' does not exist");
			$this->info(
				'If you have defined namespaced migrations, you must either provide a namespace using the '
				. "`--namespace` flag when running this command, or define a namespace named 'default' which "
				. 'will be used when no namespace is provided.',
			);

			return false;
		}

		return $migrationNamespaces['default'];
	}

	protected function runMigrations(
		string $namespace,
		array $migrations,
		Database $db,
		Connection $conn,
		bool $showStacktrace,
		bool $apply,
		bool $tableExists,
	): int {
		$this->begin($db);

		if (!$tableExists) {
			$result = $this->createMigrationsTable();

			if ($result !== 0) {
				if ($this->supportsTransactions()) {
					$db->rollback();
				}

				return $result;
			}
		}

		$appliedMigrations = $this->getAppliedMigrations($db);
		$result = self::STARTED;
		$numApplied = 0;

		foreach ($migrations as $migration) {
			assert(is_string($migration) && $migration !== '', 'Migration path must be a non-empty string.');

			$migrationId = $this->migrationId($namespace, $migration);

			if (in_array($migrationId, $appliedMigrations, strict: true)) {
				continue;
			}

			if (!$this->supportedByDriver($migration)) {
				continue;
			}

			$script = file_get_contents($migration);

			if ($script === false) {
				$this->showMessage($migration, new RuntimeException('Could not read migration file'));
				$result = self::ERROR;

				break;
			}

			if (trim($script) === '') {
				$this->showEmptyMessage($migration);
				$result = self::WARNING;

				continue;
			}

			$result = match (pathinfo($migration, PATHINFO_EXTENSION)) {
				'sql' => $this->migrateSQL($db, $namespace, $migration, $script, $showStacktrace),
				'tpql' => $this->migrateTPQL($db, $conn, $namespace, $migration, $showStacktrace),
				'php' => $this->migratePHP($db, $namespace, $migration, $showStacktrace),
			};

			if ($result === self::ERROR) {
				break;
			}

			if ($result === self::SUCCESS) {
				$numApplied++;
			}
		}

		return $this->finish($db, $result, $apply, $numApplied);
	}

	protected function begin(Database $db): void
	{
		if ($this->supportsTransactions()) {
			$db->begin();
		}
	}

	protected function createMigrationsTable(): int
	{
		$createMigrationTableCmd = new CreateMigrationsTable($this->env->conn, $this->env->options);
		$result = $createMigrationTableCmd->run();

		if ($result === 0) {
			return 0;
		}

		// Would require simulating a failing CreateMigrationsTable command
		// without a test seam or altering the public API.
		// @codeCoverageIgnoreStart
		$this->error('Migration table could not be created.');

		return is_int($result) ? $result : 1;

		// @codeCoverageIgnoreEnd
	}

	protected function finish(
		Database $db,
		string $result,
		bool $apply,
		int $numApplied,
	): int {
		$plural = $numApplied > 1 ? 's' : '';

		if ($this->supportsTransactions()) {
			if ($result === self::ERROR) {
				$db->rollback();
				echo "\nDue to errors no migrations applied\n";

				return 1;
			}

			if ($numApplied === 0) {
				$db->rollback();
				echo "\nNo migrations applied\n";

				return 0;
			}

			if ($apply) {
				$db->commit();
				echo "\n{$numApplied} migration{$plural} successfully applied\n";

				return 0;
			}
			echo "\n\033[1;31mNotice\033[0m: Test run only\033[0m";
			echo "\nWould apply {$numApplied} migration{$plural}. ";
			echo "Use the switch --apply to make it happen\n";
			$db->rollback();

			return 0;
		}

		if ($result === self::ERROR) {
			echo "\n{$numApplied} migration{$plural} applied until the error occured\n";

			return 1;
		}

		if ($numApplied > 0) {
			echo "\n{$numApplied} migration{$plural} successfully applied\n";

			return 0;
		}

		echo "\nNo migrations applied\n";

		return 0;
	}

	protected function supportsTransactions(): bool
	{
		switch ($this->env->driver) {
			case 'sqlite':
				return true;
			case 'pgsql':
				return true;
			case 'mysql':
				return false;
		}

		// An unsupported driver would have to be installed
		// to be able to test meaningfully
		// @codeCoverageIgnoreStart
		throw new RuntimeException('Database driver not supported');

		// @codeCoverageIgnoreEnd
	}

	/**
	 * @return list<string>
	 */
	protected function getAppliedMigrations(Database $db): array
	{
		$table = $this->env->table;
		$column = $this->env->columnMigration;
		$migrations = $db->execute("SELECT {$column} AS migration FROM {$table};")->all();

		return array_values(array_map(
			static fn(array $mig): string => (string) $mig['migration'],
			$migrations,
		));
	}

	protected function planMigrations(string $namespace, bool $tableExists): int
	{
		$migrations = $this->migrationsForNamespace($namespace);

		if ($migrations === false) {
			return 1;
		}

		$namespace = $namespace ?: 'default';
		$appliedMigrations = $tableExists ? $this->getAppliedMigrations($this->env->db) : [];
		$pendingMigrations = $this->pendingMigrations($namespace, $migrations, $appliedMigrations);
		$numPending = count($pendingMigrations);
		$plural = $numPending > 1 ? 's' : '';

		echo "\n\033[1;31mNotice\033[0m: Test run only\033[0m\n";

		if (!$tableExists) {
			echo "Would create migrations table '{$this->env->table}'\n";
		}

		if ($numPending === 0) {
			echo "\nNo migrations applied\n";
		} else {
			echo "Would apply {$numPending} migration{$plural}:\n";

			foreach ($pendingMigrations as $migration) {
				echo '  - ' . basename($migration) . "\n";
			}
		}

		echo "\nMySQL migrations are not executed during dry runs because Quma cannot safely ";
		echo "roll back the full migration batch on this driver. Use --apply to run them.\n";

		return 0;
	}

	/**
	 * @param list<string> $migrations
	 * @param list<string> $appliedMigrations
	 *
	 * @return list<string>
	 */
	protected function pendingMigrations(
		string $namespace,
		array $migrations,
		array $appliedMigrations,
	): array {
		$pending = [];

		foreach ($migrations as $migration) {
			assert($migration !== '', 'Migration path must be a non-empty string.');

			if (in_array($this->migrationId($namespace, $migration), $appliedMigrations, strict: true)) {
				continue;
			}

			if (!$this->supportedByDriver($migration)) {
				continue;
			}

			$pending[] = $migration;
		}

		return $pending;
	}

	protected function migrationId(string $namespace, string $migration): string
	{
		$name = basename($migration);

		if ($namespace === 'default') {
			return $name;
		}

		return $namespace . ':' . $name;
	}

	/**
	 * Returns if the given migration is driver specific.
	 */
	protected function supportedByDriver(string $migration): bool
	{
		// First checks if there are brackets in the filename.
		if (preg_match('/\[[a-z]{3,8}\]/', $migration)) {
			// We have found a driver specific migration.
			// Check if it matches the current driver.
			if (preg_match('/\[' . $this->env->driver . '\]/', $migration)) {
				return true;
			}

			return false;
		}

		// This is no driver specific migration
		return true;
	}

	protected function migrateSQL(
		Database $db,
		string $namespace,
		string $migration,
		string $script,
		bool $showStacktrace,
	): string {
		try {
			$db->execute($script)->run();
			$this->logMigration($db, $namespace, $migration);
			$this->showMessage($migration);

			return self::SUCCESS;
		} catch (PDOException $e) {
			$this->showMessage($migration, $e, $showStacktrace);

			return self::ERROR;
		}
	}

	protected function migrateTPQL(
		Database $db,
		Connection $conn,
		string $namespace,
		string $migration,
		bool $showStacktrace,
	): string {
		try {
			$context = [
				'driver' => $db->getPdoDriver(),
				'db' => $db,
				'conn' => $conn,
			];

			$executeTemplate = static function (
				string $migrationPath,
				array $context,
			): void {
				extract($context, EXTR_SKIP);

				/** @psalm-suppress UnresolvableInclude */
				require $migrationPath;
			};

			if (!is_file($migration)) {
				throw new RuntimeException('Could not read migration file');
			}

			ob_start();
			$script = '';

			try {
				$executeTemplate($migration, $context);
				$script = ob_get_contents();
			} finally {
				ob_end_clean();
			}

			if (!is_string($script) || trim($script) === '') {
				$this->showEmptyMessage($migration);

				return self::WARNING;
			}

			return $this->migrateSQL($db, $namespace, $migration, $script, $showStacktrace);
		} catch (Throwable $e) {
			$this->showMessage($migration, $e, $showStacktrace);

			return self::ERROR;
		}
	}

	protected function migratePHP(
		Database $db,
		string $namespace,
		string $migration,
		bool $showStacktrace,
	): string {
		try {
			$migObj = $this->loadPhpMigration($migration);
			$migObj->run($this->env);
			$this->logMigration($db, $namespace, $migration);
			$this->showMessage($migration);

			return self::SUCCESS;
		} catch (Throwable $e) {
			$this->showMessage($migration, $e, $showStacktrace);

			return self::ERROR;
		}
	}

	protected function loadPhpMigration(string $migration): MigrationInterface
	{
		if (!is_file($migration)) {
			throw new RuntimeException('Could not read migration file');
		}

		$migrationObject = require $migration;

		if (!$migrationObject instanceof MigrationInterface) {
			throw new RuntimeException('Invalid migration file. Expected MigrationInterface instance');
		}

		return $migrationObject;
	}

	protected function logMigration(Database $db, string $namespace, string $migration): void
	{
		$table = $this->env->table;
		$column = $this->env->columnMigration;

		$db->execute(
			"INSERT INTO {$table} ({$column}) VALUES (:migration)",
			['migration' => $this->migrationId($namespace, $migration)],
		)->run();
	}

	protected function showEmptyMessage(string $migration): void
	{
		echo
			"\033[33mWarning\033[0m: Migration '\033[1;33m"
				. basename($migration)
				. "'\033[0m is empty. Skipped\n"
		;
	}

	protected function showMessage(
		string $migration,
		?Throwable $e = null,
		bool $showStacktrace = false,
	): void {
		if ($e) {
			echo
				"\033[1;31mError\033[0m: while working on migration '\033[1;33m"
					. basename($migration)
					. "\033[0m'\n"
			;
			echo $e->getMessage() . "\n";

			if ($showStacktrace) {
				echo $e->getTraceAsString() . "\n";
			}

			return;
		}

		echo
			"\033[1;32mSuccess\033[0m: Migration '\033[1;33m"
				. basename($migration)
				. "\033[0m' successfully applied\n"
		;
	}
}

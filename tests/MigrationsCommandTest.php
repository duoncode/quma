<?php

declare(strict_types=1);

namespace Duon\Quma\Tests;

use Duon\Quma\Commands\Migrations;
use Duon\Quma\Connection;
use Duon\Quma\Database;
use PDO;
use ReflectionMethod;
use RuntimeException;

/**
 * @internal
 */
class MigrationsCommandTest extends TestCase
{
	public function testRunMigrationsHandlesUnreadableFile(): void
	{
		$_SERVER['argv'] = ['run'];
		$conn = $this->connection();
		$db = new Database($conn);
		$db->execute('DROP TABLE IF EXISTS migrations')->run();
		$db->execute('CREATE TABLE migrations (migration text, applied text)')->run();

		$missing = sys_get_temp_dir() . '/missing-migration.sql';
		if (is_file($missing)) {
			unlink($missing);
		}

		$command = new Migrations($conn);
		$method = new ReflectionMethod(Migrations::class, 'runMigrations');

		$handler = set_error_handler(static fn(): bool => true);
		try {
			ob_start();
			$result = $method->invoke($command, 'default', [$missing], false, true, true);
			$output = ob_get_contents();
			ob_end_clean();
		} finally {
			if ($handler !== null) {
				restore_error_handler();
			}
		}

		$this->assertSame(1, $result);
		$this->assertStringContainsString('Could not read migration file', $output);
	}

	public function testMigrateTpqlHandlesMissingFile(): void
	{
		$_SERVER['argv'] = ['run'];
		$conn = $this->connection();
		$db = new Database($conn);
		$command = new Migrations($conn);
		$method = new ReflectionMethod(Migrations::class, 'migrateTPQL');

		$missing = sys_get_temp_dir() . '/missing-migration-' . uniqid() . '.tpql';
		if (is_file($missing)) {
			unlink($missing);
		}

		ob_start();
		$result = $method->invoke($command, $db, $conn, 'default', $missing, false);
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertSame('error', $result);
		$this->assertStringContainsString('Could not read migration file', $output);
	}

	public function testLoadPhpMigrationThrowsWhenFileMissing(): void
	{
		$_SERVER['argv'] = ['run'];
		$conn = $this->connection();
		$command = new Migrations($conn);
		$method = new ReflectionMethod(Migrations::class, 'loadPhpMigration');

		$missing = sys_get_temp_dir() . '/missing-migration-' . uniqid() . '.php';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not read migration file');

		$method->invoke($command, $missing);
	}

	public function testLoadPhpMigrationThrowsWhenFileReturnsWrongObject(): void
	{
		$_SERVER['argv'] = ['run'];
		$conn = $this->connection();
		$command = new Migrations($conn);
		$method = new ReflectionMethod(Migrations::class, 'loadPhpMigration');

		$migration = sys_get_temp_dir() . '/invalid-migration-' . uniqid() . '.php';
		file_put_contents($migration, '<?php return new stdClass();');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Invalid migration file');

		try {
			$method->invoke($command, $migration);
		} finally {
			if (is_file($migration)) {
				unlink($migration);
			}
		}
	}

	public function testMysqlDryRunPlansPendingMigrationsWithoutRunningThem(): void
	{
		if (!in_array('mysql', PDO::getAvailableDrivers(), strict: true)) {
			$this->markTestSkipped('PDO MySQL is not available.');
		}

		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'quma-mysql-plan-' . uniqid();
		mkdir($dir, 0o700, true);
		file_put_contents(
			$dir . '/000001-plan.sql',
			'CREATE TABLE mysql_plan_should_not_run (id integer);',
		);

		$_SERVER['argv'] = ['run'];
		$conn = new Connection(
			'mysql:host=localhost;dbname=quma;user=quma;password=quma',
			$this->getSqlDirs(),
			$dir,
		);
		$command = new Migrations($conn);
		$method = new ReflectionMethod(Migrations::class, 'planMigrations');

		try {
			ob_start();
			$result = $method->invoke($command, '', false);
			$output = ob_get_contents();
			ob_end_clean();
		} finally {
			$this->removeMigrationDir($dir);
		}

		$this->assertSame(0, $result);
		$this->assertStringContainsString("Would create migrations table 'migrations'", $output);
		$this->assertStringContainsString('Would apply 1 migration', $output);
		$this->assertStringContainsString('000001-plan.sql', $output);
		$this->assertStringContainsString('MySQL migrations are not executed during dry runs', $output);
	}

	public function testPendingMigrationsSkipsAppliedAndUnsupportedDriverFiles(): void
	{
		$_SERVER['argv'] = ['run'];
		$conn = $this->connection();
		$command = new Migrations($conn);
		$method = new ReflectionMethod(Migrations::class, 'pendingMigrations');

		$result = $method->invoke(
			$command,
			'default',
			[
				'000001-applied.sql',
				'000002-pgsql-[pgsql].sql',
				'000003-sqlite-[sqlite].sql',
			],
			['000001-applied.sql'],
		);

		$this->assertSame(['000003-sqlite-[sqlite].sql'], $result);
	}

	public function testFinishHandlesNonTransactionalDrivers(): void
	{
		$_SERVER['argv'] = ['run'];
		$mysqlConn = new Connection(
			'mysql:host=localhost;dbname=quma;user=quma;password=quma',
			$this->getSqlDirs(),
			TestCase::root() . 'migrations',
		);
		$command = new Migrations($mysqlConn);
		$db = new Database($mysqlConn);
		$method = new ReflectionMethod(Migrations::class, 'finish');

		ob_start();
		$resultError = $method->invoke($command, $db, 'error', true, 2);
		$outputError = ob_get_contents();
		ob_end_clean();

		$this->assertSame(1, $resultError);
		$this->assertStringContainsString('2 migrations applied until the error occured', $outputError);

		ob_start();
		$resultSuccess = $method->invoke($command, $db, 'success', true, 2);
		$outputSuccess = ob_get_contents();
		ob_end_clean();

		$this->assertSame(0, $resultSuccess);
		$this->assertStringContainsString('2 migrations successfully applied', $outputSuccess);

		ob_start();
		$resultEmpty = $method->invoke($command, $db, 'success', true, 0);
		$outputEmpty = ob_get_contents();
		ob_end_clean();

		$this->assertSame(0, $resultEmpty);
		$this->assertStringContainsString('No migrations applied', $outputEmpty);
	}

	public function testSupportsTransactionsForPgsql(): void
	{
		$_SERVER['argv'] = ['run'];
		$pgsqlConn = new Connection(
			'pgsql:host=localhost;dbname=quma;user=quma;password=quma',
			$this->getSqlDirs(),
			TestCase::root() . 'migrations',
		);
		$command = new Migrations($pgsqlConn);
		$method = new ReflectionMethod(Migrations::class, 'supportsTransactions');

		$this->assertTrue($method->invoke($command));
	}

	private function removeMigrationDir(string $dir): void
	{
		$files = glob($dir . '/*');

		if (is_array($files)) {
			foreach ($files as $file) {
				if (!is_file($file)) {
					continue;
				}

				unlink($file);
			}
		}

		if (is_dir($dir)) {
			rmdir($dir);
		}
	}
}

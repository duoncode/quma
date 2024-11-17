<?php

/**
 * Migration testing is hard.
 *
 * Some of these tests depend on each other and the order
 * in which they are executed. Reorganize with care.
 *
 * Running a single test with '->only()' might be impossible.
 */

declare(strict_types=1);

use FiveOrbs\Cli\Runner;
use FiveOrbs\Quma\Tests\TestCase;

uses(TestCase::class);

beforeAll(function () {
	// Remove remnants of previous runs
	$migrationsDir = TestCase::root() . '/migrations/';
	array_map('unlink', glob("{$migrationsDir}*test-migration*"));

	TestCase::cleanupTestDbs();
});

afterEach(function () {
	// Each Runner::run call registers a error handler
	restore_error_handler();
	restore_exception_handler();
});

dataset('connections', TestCase::getAvailableDsns());
dataset('transaction-connections', TestCase::getAvailableDsns(transactionsOnly: true));

test('Run migrations :: no migrations table', function () {
	$_SERVER['argv'] = ['run', 'migrations', '--apply'];

	ob_start();
	$result = (new Runner($this->commands()))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(1);
	expect($content)->toContain('Migrations table does not exist');
});

test('Create migrations table :: success', function (string $dsn) {
	$_SERVER['argv'] = ['run', 'create-migrations-table'];

	ob_start();
	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	ob_end_clean();

	expect($result)->toBe(0);
})->with('connections');

test('Create migrations table :: already exists', function (string $dsn) {
	$_SERVER['argv'] = ['run', 'create-migrations-table'];

	ob_start();
	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(1);

	if (str_starts_with($dsn, 'pgsql')) {
		expect($content)->toContain("Table 'public.migrations' already exists");
	} else {
		expect($content)->toContain("Table 'migrations' already exists");
	}
})->with('connections');

test('Create migrations table :: already exists connection as arg', function () {
	$_SERVER['argv'] = ['run', 'create-migrations-table', '--conn', 'first'];

	ob_start();
	$result = (new Runner($this->commands(
		multipleConnections: true,
		firstMultipleConnectionsKey: 'first',
	)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(1);
	expect($content)->toContain("Table 'migrations' already exists");
});

test('Create migrations table :: already exists multiconnection with default', function () {
	$_SERVER['argv'] = ['run', 'create-migrations-table'];

	ob_start();
	$result = (new Runner($this->commands(
		multipleConnections: true,
		firstMultipleConnectionsKey: 'default',
	)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(1);
	expect($content)->toContain("Table 'migrations' already exists");
});

test('Create migrations table :: alternate connection', function () {
	$_SERVER['argv'] = ['run', 'create-migrations-table', '--conn', 'second'];

	ob_start();
	$result = (new Runner($this->commands(multipleConnections: true)))->run();
	ob_end_clean();

	expect($result)->toBe(0);
});

test('Create migrations table :: already exists alternate connection', function () {
	$_SERVER['argv'] = ['run', 'create-migrations-table', '--conn', 'second'];

	ob_start();
	$result = (new Runner($this->commands(multipleConnections: true)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(1);
	expect($content)->toContain("Table 'migrations' already exists");
});

test('Wrong connection', function () {
	$_SERVER['argv'] = ['run', 'create-migrations-table', '--conn', 'doesnotexist'];

	(new Runner($this->commands(multipleConnections: true)))->run();
})->throws(RuntimeException::class, 'doesnotexist');

test('Run migrations :: no migrations directories defined', function () {
	$_SERVER['argv'] = ['run', 'migrations', '--apply'];

	ob_start();
	$result = (new Runner($this->commands(migrations: [])))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(1);
	expect($content)->toContain('No migration directories defined');
});

test('Run migrations :: success without apply', function (string $dsn) {
	$_SERVER['argv'] = ['run', 'migrations'];
	$driver = strtok($dsn, ':');

	ob_start();
	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(0);
	expect($content)->toMatch('/000000-000000-migration.sql[^\n]*?success/');
	expect($content)->toMatch('/000000-000001-migration.php[^\n]*?success/');
	expect($content)->toMatch('/000000-000002-migration.tpql[^\n]*?success/');
	expect($content)->toMatch('/000000-000005-migration-\[' . $driver . '\].sql[^\n]*?success/');
	expect($content)->toContain('Would apply 4 migrations');
})->with('transaction-connections');

test('Run migrations :: success', function (string $dsn) {
	$_SERVER['argv'] = ['run', 'migrations', '--apply'];
	$driver = strtok($dsn, ':');

	ob_start();
	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(0);
	expect($content)->toMatch('/000000-000000-migration.sql[^\n]*?success/');
	expect($content)->toMatch('/000000-000001-migration.php[^\n]*?success/');
	expect($content)->toMatch('/000000-000002-migration.tpql[^\n]*?success/');
	expect($content)->toMatch('/000000-000005-migration-\[' . $driver . '\].sql[^\n]*?success/');
	expect($content)->toContain('4 migrations successfully applied');
})->with('connections');

test('Run migrations :: again', function (string $dsn) {
	$_SERVER['argv'] = ['run', 'migrations', '--apply'];

	ob_start();
	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	expect($result)->toBe(0);
	expect($content)->not->toMatch('/000000-000000-migration.sql[^\n]*?success/');
	expect($content)->toContain('No migrations applied');
})->with('connections');

test('Add migration SQL', function () {
	$_SERVER['argv'] = ['run', 'add-migration', '--file', 'test migration'];

	ob_start();
	$migration = (new Runner($this->commands()))->run();
	ob_end_clean();

	expect(is_file($migration))->toBe(true);
	expect(str_starts_with($migration, TestCase::root()))->toBe(true);
	expect(str_ends_with($migration, '.sql'))->toBe(true);

	// Add content and run it
	file_put_contents($migration, 'SELECT 1;');
	$_SERVER['argv'] = ['run', 'migrations', '--apply'];

	ob_start();
	$result = (new Runner($this->commands()))->run();
	$content = ob_get_contents();
	ob_end_clean();
	@unlink($migration);

	expect(is_file($migration))->toBe(false);
	expect($result)->toBe(0);
	expect($content)->toMatch('/' . basename($migration) . '[^\n]*?success/');
	expect($content)->toContain('1 migration successfully applied');
});

test('Add migration TPQL', function () {
	$_SERVER['argv'] = ['run', 'add-migration', '--file', 'test migration.tpql'];

	ob_start();
	$migration = (new Runner($this->commands()))->run();
	ob_end_clean();

	expect(is_file($migration))->toBe(true);
	expect(str_starts_with($migration, TestCase::root()))->toBe(true);
	expect(str_ends_with($migration, '.tpql'))->toBe(true);
	expect(strpos($migration, '.sql'))->toBe(false);

	$content = file_get_contents($migration);

	@unlink($migration);
	expect(is_file($migration))->toBe(false);
	expect($content)->toContain('<?php if');
});

test('Add migration PHP', function () {
	$_SERVER['argv'] = ['run', 'add-migration', '--file', 'test migration.php'];

	ob_start();
	$migration = (new Runner($this->commands()))->run();
	ob_end_clean();

	expect(is_file($migration))->toBe(true);
	expect(str_starts_with($migration, TestCase::root()))->toBe(true);
	expect(str_ends_with($migration, '.php'))->toBe(true);
	expect(strpos($migration, '.sql'))->toBe(false);

	$content = file_get_contents($migration);

	@unlink($migration);
	expect(is_file($migration))->toBe(false);
	expect($content)->toContain('TestMigration_');
	expect($content)->toContain('implements MigrationInterface');
});

test('Add migration with wrong file extension', function () {
	$_SERVER['argv'] = ['run', 'add-migration', '-f', 'test.exe'];

	ob_start();
	(new Runner($this->commands()))->run();
	$output = ob_get_contents();
	ob_end_clean();

	expect($output)->toContain('Wrong file extension');
});

test('Wrong migrations directory', function () {
	$this->connection(migrations: 'not/available');
})->throws(ValueError::class, 'Path does not exist: not/available');

test('Add migration to vendor', function () {
	$_SERVER['argv'] = ['run', 'add-migration', '-f', 'test'];

	ob_start();
	(new Runner($this->commands(migrations: TestCase::root() . '/../vendor')))->run();
	$output = ob_get_contents();
	ob_end_clean();

	expect($output)->toContain("is inside './vendor'");
});

test('Failing SQL migration', function ($dsn, $ext) {
	$_SERVER['argv'] = ['run', 'add-migration', '--file', "test-migration-failing{$ext}"];

	ob_start();
	$migration = (new Runner($this->commands(dsn: $dsn)))->run();

	// Add content and run it
	file_put_contents($migration, 'RUBBISH;');
	$_SERVER['argv'] = ['run', 'migrations', '--apply', '--stacktrace'];

	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	$content = ob_get_contents();
	ob_end_clean();
	@unlink($migration);

	expect(is_file($migration))->toBe(false);
	expect($result)->toBe(1);
	expect($content)->toContain("\n#0");

	if (str_starts_with($dsn, 'mysql')) {
		expect($content)->toContain('0 migration applied until the error occured');
		expect($content)->toContain('SQLSTATE[42000]');
	} elseif (str_starts_with($dsn, 'pgsql')) {
		expect($content)->toContain('Due to errors no migrations applied');
		expect($content)->toContain('SQLSTATE[42601]');
	} else {
		expect($content)->toContain('Due to errors no migrations applied');
		expect($content)->toContain('SQLSTATE[HY000]');
	}
})->with('connections')->with(['.sql', '.tpql']);

test('Failing TPQL/PHP migration (PHP error)', function ($dsn, $ext) {
	$_SERVER['argv'] = ['run', 'add-migration', '--file', "test-migration-php-failing.{$ext}"];

	ob_start();
	$migration = (new Runner($this->commands(dsn: $dsn)))->run();

	// Add content and run it
	file_put_contents($migration, '<?php echo if)');
	$_SERVER['argv'] = ['run', 'migrations', '--apply'];

	$result = (new Runner($this->commands(dsn: $dsn)))->run();
	$content = ob_get_contents();
	ob_end_clean();
	@unlink($migration);

	expect(is_file($migration))->toBe(false);
	expect($result)->toBe(1);

	if (str_starts_with($dsn, 'mysql')) {
		expect($content)->toContain('0 migration applied until the error occured');
	} else {
		expect($content)->toContain('Due to errors no migrations applied');
	}
})->with('connections')->with(['.php', '.tpql']);

test('Failing due to readonly migrations directory', function () {
	$tmpdir = sys_get_temp_dir() . '/chuck' . (string) mt_rand();
	mkdir($tmpdir, 0400);

	$_SERVER['argv'] = ['run', 'add-migration', '--file', 'test-migration.sql'];

	ob_start();
	(new Runner($this->commands(migrations: $tmpdir)))->run();
	$content = ob_get_contents();
	ob_end_clean();

	rmdir($tmpdir);

	expect($content)->toContain('directory is not writable');
});

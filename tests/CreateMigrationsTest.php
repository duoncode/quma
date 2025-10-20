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

use Duon\Cli\Runner;
use Duon\Quma\Tests\TestCase;

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

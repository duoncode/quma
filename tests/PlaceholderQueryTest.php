<?php

declare(strict_types=1);

namespace Duon\Quma\Tests;

use Duon\Quma\Connection;
use Duon\Quma\Database;
use Duon\Quma\Delimiters;
use PDO;
use RuntimeException;

/**
 * @internal
 */
class PlaceholderQueryTest extends TestCase
{
	/** @var list<string> */
	private array $tempDirs = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::createTestDb();
	}

	protected function tearDown(): void
	{
		foreach ($this->tempDirs as $dir) {
			$this->removeDir($dir);
		}

		$this->tempDirs = [];
		parent::tearDown();
	}

	public function testSqlFileUsesPlaceholdersAndRuntimeParameters(): void
	{
		$dir = $this->createSqlDir();
		file_put_contents(
			$dir . '/music/byMember.sql',
			'SELECT name FROM [::table::] WHERE member = :member;',
		);

		$db = new Database(new Connection($this->getDsn(), $dir)->placeholders([
			'all' => ['table' => 'albums'],
			'sqlite' => ['table' => 'members'],
		]));

		$result = $db->music->byMember(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);

		$this->assertSame('Chuck Schuldiner', $result['name']);
	}

	public function testSqlFileUsesCustomDelimiters(): void
	{
		$dir = $this->createSqlDir();
		file_put_contents(
			$dir . '/music/byMemberCustom.sql',
			'SELECT name FROM [[table]] WHERE member = :member;',
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)
				->delimiters(new Delimiters('[[', ']]'))
				->placeholders(['all' => ['table' => 'members']]),
		);

		$result = $db->music->byMemberCustom(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);

		$this->assertSame('Chuck Schuldiner', $result['name']);
	}

	public function testDebugTranslatedWritesRuntimeQueryFile(): void
	{
		$dir = $this->createSqlDir();
		$debugDir = $this->createTempDir('quma-translated-');
		file_put_contents(
			$dir . '/music/debug.sql',
			'SELECT name FROM [::table::] WHERE member = :member;',
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)->placeholders(['all' => ['table' => 'members']]),
		)->debug(true);

		$this->withEnv('QUMA_DEBUG_TRANSLATED', $debugDir, static function () use ($db): void {
			$db->music->debug(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
		});

		$files = glob($debugDir . '/*/????--music--debug.sql');
		$this->assertIsArray($files);
		$this->assertCount(1, $files);
		$this->assertMatchesRegularExpression(
			'/\/\d{8}-\d{6}-\d{6}--cli--[a-f0-9]{8}\/\d{4}--music--debug\.sql$/',
			$files[0],
		);
		$this->assertSame(
			'SELECT name FROM members WHERE member = :member;',
			file_get_contents($files[0]),
		);
	}

	public function testDebugTranslatedWritesRenderedTemplatePerInvocation(): void
	{
		$dir = $this->createSqlDir();
		$debugDir = $this->createTempDir('quma-translated-');
		file_put_contents(
			$dir . '/music/dynamic.tpql',
			<<<'TPQL'
				SELECT name
				FROM members
				WHERE member = :member
				<?php if (($joinedAfter ?? null) !== null) : ?>
				AND joined > :joinedAfter
				<?php endif ?>
				TPQL,
		);

		$db = new Database(new Connection($this->getDsn(), $dir))->debug(true);

		$this->withEnv('QUMA_DEBUG_TRANSLATED', $debugDir, static function () use ($db): void {
			$db->music->dynamic(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
			$db->music->dynamic(['member' => 1, 'joinedAfter' => 1980])->one(fetchMode: PDO::FETCH_ASSOC);
		});

		$files = glob($debugDir . '/*/????--music--dynamic.sql');
		$this->assertIsArray($files);
		sort($files);
		$this->assertCount(2, $files);
		$this->assertStringNotContainsString(
			'AND joined > :joinedAfter',
			(string) file_get_contents($files[0]),
		);
		$this->assertStringContainsString(
			'AND joined > :joinedAfter',
			(string) file_get_contents($files[1]),
		);
	}

	public function testDebugSessionUsesHttpRequestInfo(): void
	{
		$dir = $this->createSqlDir();
		$debugDir = $this->createTempDir('quma-http-debug-');
		file_put_contents(
			$dir . '/music/debug.sql',
			'SELECT name FROM members WHERE member = :member;',
		);

		$db = new Database(new Connection($this->getDsn(), $dir))->debug(true);

		$this->withEnv('QUMA_DEBUG_TRANSLATED', $debugDir, function () use ($db): void {
			$this->withServer(
				[
					'REQUEST_METHOD' => 'GET',
					'REQUEST_URI' => '/admin/users?token=secret',
					'REQUEST_TIME_FLOAT' => '1800000000.123456',
				],
				static function () use ($db): void {
					$db->music->debug(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
				},
			);
			$this->withServer(
				[
					'REQUEST_METHOD' => 'GET',
					'REQUEST_URI' => '/admin/users?token=secret',
					'REQUEST_TIME_FLOAT' => '1800000001.654321',
				],
				static function () use ($db): void {
					$db->music->debug(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
				},
			);
		});

		$files = glob($debugDir . '/*--GET--admin-users--*/0001--music--debug.sql');
		$this->assertIsArray($files);
		sort($files);
		$this->assertCount(2, $files);
		$this->assertStringNotContainsString('secret', $files[0]);
		$this->assertStringNotContainsString('secret', $files[1]);
	}

	public function testDebugInterpolatedWritesRuntimeQueryFile(): void
	{
		$dir = $this->createSqlDir();
		$debugDir = $this->createTempDir('quma-interpolated-');
		file_put_contents(
			$dir . '/music/debug.sql',
			'SELECT name FROM members WHERE member = :member;',
		);

		$db = new Database(new Connection($this->getDsn(), $dir))->debug(true);

		$this->withEnv('QUMA_DEBUG_INTERPOLATED', $debugDir, static function () use ($db): void {
			$db->music->debug(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
		});

		$files = glob($debugDir . '/*/????--music--debug.sql');
		$this->assertIsArray($files);
		$this->assertCount(1, $files);
		$this->assertMatchesRegularExpression(
			'/\/\d{8}-\d{6}-\d{6}--cli--[a-f0-9]{8}\/\d{4}--music--debug\.sql$/',
			$files[0],
		);
		$this->assertStringContainsString(
			'SELECT name FROM members WHERE member = 1;',
			(string) file_get_contents($files[0]),
		);
	}

	public function testSqlFilesAreCompiledOncePerDatabaseInstance(): void
	{
		$dir = $this->createSqlDir();
		$file = $dir . '/music/cached.sql';
		file_put_contents(
			$file,
			"SELECT 'before' AS value FROM [::table::] LIMIT 1;",
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)->placeholders(['all' => ['table' => 'members']]),
		);

		$this->assertSame('before', $db->music->cached()->one(fetchMode: PDO::FETCH_ASSOC)['value']);

		file_put_contents(
			$file,
			"SELECT 'after' AS value FROM [::table::] LIMIT 1;",
		);

		$this->assertSame('before', $db->music->cached()->one(fetchMode: PDO::FETCH_ASSOC)['value']);
	}

	public function testTemplateFileUsesPlaceholdersBeforeRendering(): void
	{
		$dir = $this->createSqlDir();
		file_put_contents(
			$dir . '/music/dynamic.tpql',
			<<<'TPQL'
				SELECT name
				FROM [::table::]
				WHERE member = :member
				<?php if (($joinedAfter ?? null) !== null) : ?>
				AND joined > :joinedAfter
				<?php endif ?>
				TPQL,
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)->placeholders(['all' => ['table' => 'members']]),
		);

		$result = $db->music->dynamic([
			'member' => 1,
			'joinedAfter' => 1980,
		])->one(fetchMode: PDO::FETCH_ASSOC);

		$this->assertSame('Chuck Schuldiner', $result['name']);
	}

	public function testTemplateFileUsesCustomDelimitersBeforeRendering(): void
	{
		$dir = $this->createSqlDir();
		file_put_contents(
			$dir . '/music/custom.tpql',
			<<<'TPQL'
				SELECT name
				FROM [[table]]
				WHERE member = :member
				TPQL,
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)
				->delimiters(new Delimiters('[[', ']]'))
				->placeholders(['all' => ['table' => 'members']]),
		);

		$result = $db->music->custom(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);

		$this->assertSame('Chuck Schuldiner', $result['name']);
	}

	public function testTemplateQueryUsesConfiguredCacheDir(): void
	{
		$dir = $this->createSqlDir();
		$cacheDir = $this->createTempDir('quma-cache-');
		file_put_contents(
			$dir . '/music/cached.tpql',
			"SELECT '[::value::]' AS value;",
		);

		$conn = new Connection($this->getDsn(), $dir)
			->placeholders(['all' => ['value' => 'cached']])
			->cache($cacheDir);
		$db = new Database($conn);

		$this->assertSame(
			'cached',
			$db->music->cached(['unused' => true])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);
		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(1, $cacheFiles);

		$this->assertSame(
			'cached',
			$db->music->cached(['unused' => true])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);
		$this->assertSame($cacheFiles, glob($cacheDir . '/tpql-*.php'));

		$this->assertSame(
			'cached',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);
		$this->assertSame($cacheFiles, glob($cacheDir . '/tpql-*.php'));
	}

	public function testTemplateCacheKeyChangesWithSourceMetadata(): void
	{
		$dir = $this->createSqlDir();
		$cacheDir = $this->createTempDir('quma-cache-');
		$file = $dir . '/music/cached.tpql';
		file_put_contents($file, "SELECT 'before' AS value;");

		$conn = new Connection($this->getDsn(), $dir)->cache($cacheDir);
		$this->assertSame(
			'before',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);
		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(1, $cacheFiles);

		file_put_contents($file, "SELECT 'after changed' AS value;");
		touch($file, time() + 2);
		clearstatcache(true, $file);

		$this->assertSame(
			'after changed',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);
		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(2, $cacheFiles);
	}

	public function testTemplateCacheKeyChangesWithPlaceholders(): void
	{
		$dir = $this->createSqlDir();
		$cacheDir = $this->createTempDir('quma-cache-');
		file_put_contents(
			$dir . '/music/cached.tpql',
			"SELECT '[::value::]' AS value;",
		);

		$conn = new Connection($this->getDsn(), $dir)
			->placeholders(['all' => ['value' => 'first']])
			->cache($cacheDir);
		$this->assertSame(
			'first',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);

		$conn = new Connection($this->getDsn(), $dir)
			->placeholders(['all' => ['value' => 'second']])
			->cache($cacheDir);
		$this->assertSame(
			'second',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);

		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(2, $cacheFiles);
	}

	public function testTemplateCacheKeyChangesWithDelimiters(): void
	{
		$dir = $this->createSqlDir();
		$cacheDir = $this->createTempDir('quma-cache-');
		file_put_contents(
			$dir . '/music/cached.tpql',
			"SELECT '[[value]]' AS value;",
		);

		$conn = new Connection($this->getDsn(), $dir)
			->placeholders(['all' => ['value' => 'cached']])
			->cache($cacheDir);
		$this->assertSame(
			'[[value]]',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);

		$conn = new Connection($this->getDsn(), $dir)
			->delimiters(new Delimiters('[[', ']]'))
			->placeholders(['all' => ['value' => 'cached']])
			->cache($cacheDir);
		$this->assertSame(
			'cached',
			new Database($conn)->music->cached([
				'unused' => true,
			])->one(fetchMode: PDO::FETCH_ASSOC)['value'],
		);

		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(2, $cacheFiles);
	}

	public function testTemplateGeneratedPlaceholdersThrowClearException(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Static placeholders inside PHP blocks or generated template output are not supported',
		);

		$dir = $this->createSqlDir();
		file_put_contents(
			$dir . '/music/bad.tpql',
			"SELECT name FROM <?= '[::table::]' ?> WHERE member = :member;",
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)->placeholders(['all' => ['table' => 'members']]),
		);

		$db->music->bad(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
	}

	public function testTemplateGeneratedCustomPlaceholdersThrowClearException(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Static placeholders inside PHP blocks or generated template output are not supported',
		);

		$dir = $this->createSqlDir();
		file_put_contents(
			$dir . '/music/bad-custom.tpql',
			"SELECT name FROM <?= '[[table]]' ?> WHERE member = :member;",
		);

		$db = new Database(
			new Connection($this->getDsn(), $dir)
				->delimiters(new Delimiters('[[', ']]'))
				->placeholders(['all' => ['table' => 'members']]),
		);

		$db->music->{'bad-custom'}(['member' => 1])->one(fetchMode: PDO::FETCH_ASSOC);
	}

	private function createSqlDir(): string
	{
		$dir = $this->createTempDir('quma-sql-');
		mkdir($dir . '/music', 0o700, true);

		return $dir;
	}

	private function createTempDir(string $prefix): string
	{
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid();
		mkdir($dir, 0o700);
		$this->tempDirs[] = $dir;

		return $dir;
	}

	private function removeDir(string $dir): void
	{
		$files = glob($dir . '/*');

		if (is_array($files)) {
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);

					continue;
				}

				if (is_dir($file)) {
					$this->removeDir($file);
				}
			}
		}

		rmdir($dir);
	}
}

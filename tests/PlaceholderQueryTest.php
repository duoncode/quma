<?php

declare(strict_types=1);

namespace Duon\Quma\Tests;

use Duon\Quma\Connection;
use Duon\Quma\Database;
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

		$result = $db->music->byMember(['member' => 1])->one(PDO::FETCH_ASSOC);

		$this->assertSame('Chuck Schuldiner', $result['name']);
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

		$this->assertSame('before', $db->music->cached()->one(PDO::FETCH_ASSOC)['value']);

		file_put_contents(
			$file,
			"SELECT 'after' AS value FROM [::table::] LIMIT 1;",
		);

		$this->assertSame('before', $db->music->cached()->one(PDO::FETCH_ASSOC)['value']);
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
		])->one(PDO::FETCH_ASSOC);

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
			$db->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
		);
		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(1, $cacheFiles);

		$this->assertSame(
			'cached',
			$db->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
		);
		$this->assertSame($cacheFiles, glob($cacheDir . '/tpql-*.php'));

		$this->assertSame(
			'cached',
			new Database($conn)->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
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
			new Database($conn)->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
		);
		$cacheFiles = glob($cacheDir . '/tpql-*.php');
		$this->assertIsArray($cacheFiles);
		$this->assertCount(1, $cacheFiles);

		file_put_contents($file, "SELECT 'after changed' AS value;");
		touch($file, time() + 2);
		clearstatcache(true, $file);

		$this->assertSame(
			'after changed',
			new Database($conn)->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
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
			new Database($conn)->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
		);

		$conn = new Connection($this->getDsn(), $dir)
			->placeholders(['all' => ['value' => 'second']])
			->cache($cacheDir);
		$this->assertSame(
			'second',
			new Database($conn)->music->cached(['unused' => true])->one(PDO::FETCH_ASSOC)['value'],
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

		$db->music->bad(['member' => 1])->one(PDO::FETCH_ASSOC);
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

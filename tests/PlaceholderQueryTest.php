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

		$db = new Database(new Connection(
			$this->getDsn(),
			$dir,
			placeholders: [
				'all' => ['table' => 'albums'],
				'sqlite' => ['table' => 'members'],
			],
		));

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

		$db = new Database(new Connection(
			$this->getDsn(),
			$dir,
			placeholders: ['all' => ['table' => 'members']],
		));

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

		$db = new Database(new Connection(
			$this->getDsn(),
			$dir,
			placeholders: ['all' => ['table' => 'members']],
		));

		$result = $db->music->dynamic([
			'member' => 1,
			'joinedAfter' => 1980,
		])->one(PDO::FETCH_ASSOC);

		$this->assertSame('Chuck Schuldiner', $result['name']);
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

		$db = new Database(new Connection(
			$this->getDsn(),
			$dir,
			placeholders: ['all' => ['table' => 'members']],
		));

		$db->music->bad(['member' => 1])->one(PDO::FETCH_ASSOC);
	}

	private function createSqlDir(): string
	{
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'quma-sql-' . uniqid();
		mkdir($dir . '/music', 0o700, true);
		$this->tempDirs[] = $dir;

		return $dir;
	}

	private function removeDir(string $dir): void
	{
		$files = glob($dir . '/music/*');

		if (is_array($files)) {
			foreach ($files as $file) {
				if (!is_file($file)) {
					continue;
				}

				unlink($file);
			}
		}

		if (is_dir($dir . '/music')) {
			rmdir($dir . '/music');
		}

		if (is_dir($dir)) {
			rmdir($dir);
		}
	}
}

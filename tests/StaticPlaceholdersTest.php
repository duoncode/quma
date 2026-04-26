<?php

declare(strict_types=1);

namespace Duon\Quma\Tests;

use Duon\Quma\StaticPlaceholders;
use InvalidArgumentException;
use RuntimeException;

/**
 * @internal
 */
class StaticPlaceholdersTest extends TestCase
{
	public function testResolvesAllAndDriverPlaceholders(): void
	{
		$placeholders = new StaticPlaceholders('sqlite', [
			'all' => [
				'prefix' => 'cms_',
				'table' => 'fallback',
			],
			'sqlite' => [
				'table' => 'members',
			],
		]);

		$this->assertSame(
			[
				'prefix' => 'cms_',
				'table' => 'members',
			],
			$placeholders->values(),
		);
		$this->assertSame(
			'SELECT * FROM cms_members',
			$placeholders->compileSql('SELECT * FROM [::prefix::][::table::]', 'query.sql'),
		);
	}

	public function testAllowsPlaceholderNamesWithSeparators(): void
	{
		$placeholders = new StaticPlaceholders('sqlite', [
			'all' => [
				'schema.name' => 'main.',
				'cms:prefix' => 'cms_',
				'tenant-prefix' => 'tenant_',
			],
		]);

		$this->assertSame(
			'main.cms_tenant_nodes',
			$placeholders->compileSql(
				'[::schema.name::][::cms:prefix::][::tenant-prefix::]nodes',
				'query.sql',
			),
		);
	}

	public function testTemplateCompilationSkipsPhpBlocks(): void
	{
		$placeholders = new StaticPlaceholders('sqlite', [
			'all' => ['table' => 'members'],
		]);
		$template = "SELECT * FROM [::table::]\n<?php echo '[::table::]'; ?>";

		$compiled = $placeholders->compileTemplate($template, 'query.tpql');

		$this->assertStringContainsString('SELECT * FROM members', $compiled);
		$this->assertStringContainsString("echo '[::table::]'", $compiled);
	}

	public function testRenderedTemplatePlaceholdersThrowClearException(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Placeholders inside PHP blocks or generated template output are not supported',
		);

		$placeholders = new StaticPlaceholders('sqlite', []);
		$placeholders->assertNoTemplatePlaceholders('SELECT * FROM [::table::]', 'query.tpql');
	}

	public function testUnknownPlaceholderThrowsHelpfulException(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Unknown static placeholder [::table::] in query.sql:1:15 for driver "sqlite"',
		);
		$this->expectExceptionMessage(
			"Add placeholders['all']['table'] or placeholders['sqlite']['table']",
		);

		$placeholders = new StaticPlaceholders('sqlite', []);
		$placeholders->compileSql('SELECT * FROM [::table::]', 'query.sql');
	}

	public function testMalformedPlaceholderThrowsHelpfulException(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Malformed static placeholder in query.sql:1:15');
		$this->expectExceptionMessage('Expected [::name::]');

		$placeholders = new StaticPlaceholders('sqlite', []);
		$placeholders->compileSql('SELECT * FROM [::table name::]', 'query.sql');
	}

	public function testDefaultScopeIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Replace placeholders['default'] with placeholders['all']");

		new StaticPlaceholders('sqlite', [
			'default' => ['prefix' => 'cms_'],
		]);
	}

	public function testFlatMapIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(
			"Static placeholders for scope 'prefix' must be an array of string values",
		);

		new StaticPlaceholders('sqlite', [
			'prefix' => 'cms_',
		]);
	}

	public function testInvalidPlaceholderNameIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid static placeholder name');

		new StaticPlaceholders('sqlite', [
			'all' => ['table name' => 'members'],
		]);
	}

	public function testNonStringValueIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Static placeholder 'prefix' in scope 'all' must be a string");

		new StaticPlaceholders('sqlite', [
			'all' => ['prefix' => 123],
		]);
	}

	public function testNestedPlaceholderValueIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must not contain another static placeholder');

		new StaticPlaceholders('sqlite', [
			'all' => ['prefix' => '[::schema::].'],
		]);
	}
}

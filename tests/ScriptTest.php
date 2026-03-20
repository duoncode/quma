<?php

declare(strict_types=1);

namespace Duon\Quma\Tests;

use Duon\Quma\Args;
use Duon\Quma\Database;
use Duon\Quma\Tests\Util\TestableScript;

/**
 * @internal
 */
class ScriptTest extends TestCase
{
	public function testEvaluateTemplateReturnsEmptyStringForMissingFile(): void
	{
		$missingFile = sys_get_temp_dir() . '/quma-missing-template-' . uniqid() . '.tpql';

		if (is_file($missingFile)) {
			unlink($missingFile);
		}

		$script = new TestableScript(new Database($this->connection()), '', true);

		$this->assertSame('', $script->evaluateTemplatePublic($missingFile, new Args([])));
	}
}

<?php

declare(strict_types=1);

namespace Duon\Quma\Tests\Util;

use Duon\Quma\Args;
use Duon\Quma\Script;

final class TestableScript extends Script
{
	public function evaluateTemplatePublic(string $path, Args $args): string
	{
		return $this->evaluateTemplate($path, $args);
	}
}

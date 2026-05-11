<?php

declare(strict_types=1);

namespace Celemas\Quma\Tests\Util;

use Celemas\Quma\Args;
use Celemas\Quma\Script;

final class TestableScript extends Script
{
	public function evaluateTemplatePublic(string $path, Args $args): string
	{
		return $this->evaluateTemplate($path, $args);
	}
}

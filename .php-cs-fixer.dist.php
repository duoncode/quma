<?php

declare(strict_types=1);

use FiveOrbs\Development\PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/docs']);
$config = new Config();

return $config->setFinder($finder);

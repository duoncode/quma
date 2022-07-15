<?php

declare(strict_types=1);

namespace Conia\Puma;

class PreparedQuery
{
    public function __construct(
        public string $query,
        public array $swaps,
    ) {
    }
}

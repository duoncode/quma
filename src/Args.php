<?php

declare(strict_types=1);

namespace Conia\Puma;

use Conia\Puma\Util;

class Args
{
    protected ArgType $type;
    protected int $count;
    protected readonly array $args;

    public function __construct(array $args)
    {
        $this->args = $this->prepare($args);
    }

    protected function prepare(array $args): array
    {

        $this->count = count($args);

        if ($this->count === 1 && is_array($args[0])) {
            if (Util::isAssoc($args[0])) {
                $this->type = ArgType::Named;
            } else {
                $this->type = ArgType::Positional;
            }

            return $args[0];
        }

        $this->type = ArgType::Positional;

        return $args;
    }

    public function get(): array
    {
        return $this->args;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function type(): ArgType
    {
        return $this->type;
    }
}

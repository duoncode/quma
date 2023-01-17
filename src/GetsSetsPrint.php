<?php

declare(strict_types=1);

namespace Conia\Quma;

trait GetsSetsPrint
{
    protected bool $print;

    public function print(bool $print = false): bool
    {
        // Normally this is bad practise but setting print should
        // only be used for debugging purposes
        if (func_num_args() > 0) {
            $this->print = $print;
        }

        return $this->print;
    }
}

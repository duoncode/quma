<?php

declare(strict_types=1);

namespace Conia\Quma;

enum ArgType
{
    case Named;

    case Positional;
}

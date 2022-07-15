<?php

declare(strict_types=1);

namespace Conia\Puma;

enum ArgType
{
    case Named;
    case Positional;
}

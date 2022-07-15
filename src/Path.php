<?php

declare(strict_types=1);

namespace Conia\Puma;

class Path
{
    public static function inside(string $parent, string $path): bool
    {
        return str_starts_with(self::realpath($path), self::realpath($parent));
    }

    public static function realpath(string $path, string $separator = DIRECTORY_SEPARATOR): string
    {
        $path = strtr($path, '\\', '/');

        do {
            $path = str_replace('//', '/', $path);
        } while (strpos($path, '//') !== false);

        $path = strtr($path, '/', $separator);

        $segments = explode($separator, $path);
        $out = [];

        foreach ($segments as $segment) {
            if ($segment == '.') {
                continue;
            }

            if ($segment == '..') {
                array_pop($out);
                continue;
            }

            $out[] = $segment;
        }

        return implode($separator, $out);
    }

    public static function isAbsolute(string $path, string $separator = DIRECTORY_SEPARATOR): bool
    {
        return str_starts_with($path, $separator);
    }
}

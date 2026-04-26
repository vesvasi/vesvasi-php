<?php

declare(strict_types=1);

namespace Vesvasi\Util;

final class PatternMatcher
{
    private static bool $compiled = false;
    private static array $includePatterns = [];
    private static array $excludePatterns = [];

    public static function shouldInclude(string $value, array $includes, array $excludes): bool
    {
        if (empty($includes) || in_array('*', $includes, true)) {
            $included = true;
        } else {
            $included = self::matchesAny($value, $includes);
        }

        if (empty($excludes)) {
            return $included;
        }

        $excluded = self::matchesAny($value, $excludes);
        return $included && !$excluded;
    }

    private static function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($value, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $value, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $regex = self::patternToRegex($pattern);
            return (bool) preg_match($regex, $value);
        }

        return $value === $pattern;
    }

    private static function patternToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\\*', '.*', $pattern);
        return '/^' . $pattern . '$/i';
    }

    public static function filterByPattern(array $items, array $includes, array $excludes): array
    {
        return array_values(array_filter($items, function ($item) use ($includes, $excludes) {
            return self::shouldInclude($item, $includes, $excludes);
        }));
    }
}
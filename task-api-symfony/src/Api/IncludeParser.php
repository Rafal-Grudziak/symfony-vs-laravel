<?php

declare(strict_types=1);

namespace App\Api;

use Symfony\Component\HttpFoundation\Request;

/**
 * Mirrors Laravel ParsesApiIncludes (query key {@see self::PRIMARY_KEY}).
 * Also accepts {@see self::ALIAS_KEY} for thesis / client convenience.
 */
final class IncludeParser
{
    private const PRIMARY_KEY = 'with';

    private const ALIAS_KEY = 'include';

    /**
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    public static function allowed(Request $request, array $allowed): array
    {
        $requested = array_merge(
            self::parseQueryKey($request, self::PRIMARY_KEY),
            self::parseQueryKey($request, self::ALIAS_KEY),
        );

        $allowedMap = array_flip($allowed);

        $out = [];
        foreach ($requested as $key) {
            if (isset($allowedMap[$key])) {
                $out[$key] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @return list<string>
     */
    private static function parseQueryKey(Request $request, string $key): array
    {
        $raw = $request->query->get($key);

        if (is_array($raw)) {
            return array_values(array_filter($raw, static fn ($v) => is_string($v) && $v !== ''));
        }

        if (is_string($raw) && $raw !== '') {
            return array_values(array_filter(array_map(trim(...), explode(',', $raw))));
        }

        return [];
    }
}

<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;

trait ParsesApiIncludes
{
    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    protected function allowedIncludes(Request $request, array $allowed): array
    {
        $raw = $request->query('with');

        if (is_array($raw)) {
            $requested = array_values(array_filter($raw, fn ($v) => is_string($v) && $v !== ''));
        } elseif (is_string($raw) && $raw !== '') {
            $requested = array_values(array_filter(array_map('trim', explode(',', $raw))));
        } else {
            return [];
        }

        $allowedMap = array_flip($allowed);

        return array_values(array_filter($requested, fn (string $key) => isset($allowedMap[$key])));
    }
}

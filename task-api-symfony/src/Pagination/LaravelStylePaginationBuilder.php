<?php

declare(strict_types=1);

namespace App\Pagination;

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds Laravel-style {@see PaginatedResourceResponse} JSON shape.
 */
final class LaravelStylePaginationBuilder
{
    /**
     * @param list<array<string, mixed>> $dataItems already resolved resource rows
     *
     * @return array<string, mixed>
     */
    public function buildWrappedCollection(Request $request, PaginatedResult $page, array $dataItems): array
    {
        $path = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
        $query = $request->query->all();
        $last = $page->lastPage();

        $url = static function (int $p) use ($path, $query): string {
            $q = array_merge($query, ['page' => $p]);

            return $path.'?'.http_build_query($q);
        };

        $current = $page->currentPage;

        return [
            'data' => $dataItems,
            'links' => [
                'first' => $url(1),
                'last' => $url($last),
                'prev' => $current > 1 ? $url($current - 1) : null,
                'next' => $current < $last ? $url($current + 1) : null,
            ],
            'meta' => [
                'current_page' => $current,
                'from' => $page->firstItem(),
                'last_page' => $last,
                'links' => $this->metaLinks($current, $last, $url),
                'path' => $path,
                'per_page' => $page->perPage,
                'to' => $page->lastItem(),
                'total' => $page->total,
            ],
        ];
    }

    /**
     * @return list<array{url: ?string, label: string, active: bool}>
     */
    private function metaLinks(int $current, int $last, \Closure $url): array
    {
        if ($last <= 0) {
            return [];
        }

        $window = 3;
        $pageSet = [];
        $pageSet[1] = true;
        $pageSet[$last] = true;

        for ($i = $current - $window; $i <= $current + $window; ++$i) {
            if ($i >= 1 && $i <= $last) {
                $pageSet[$i] = true;
            }
        }

        $pages = array_keys($pageSet);
        sort($pages, SORT_NUMERIC);

        $links = [];
        $prev = 0;

        foreach ($pages as $p) {
            if ($prev > 0 && $p > $prev + 1) {
                $links[] = ['url' => null, 'label' => '...', 'active' => false];
            }

            $links[] = ['url' => $url($p), 'label' => (string) $p, 'active' => $p === $current];
            $prev = $p;
        }

        return $links;
    }
}

<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Length-aware pagination using Doctrine's {@see Paginator}.
 *
 * @template T of object
 */
final class PaginationFactory
{
    /**
     * @return PaginatedResult<T>
     */
    public function paginate(Query $query, int $page, int $perPage, bool $fetchJoinCollection = true): PaginatedResult
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $query->setFirstResult(($page - 1) * $perPage);
        $query->setMaxResults($perPage);

        $paginator = new Paginator($query, $fetchJoinCollection);

        /** @var list<T> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new PaginatedResult($items, $paginator->count(), $page, $perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Pagination;

/**
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $currentPage,
        public readonly int $perPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function firstItem(): ?int
    {
        if ($this->total === 0) {
            return null;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function lastItem(): ?int
    {
        if ($this->total === 0) {
            return null;
        }

        return min($this->total, $this->currentPage * $this->perPage);
    }
}

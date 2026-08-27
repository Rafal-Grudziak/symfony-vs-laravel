<?php

declare(strict_types=1);

namespace App\Benchmark\NoOrm;

use App\Entity\Task;
use App\Pagination\PaginatedResult;


final class NoOrmTaskService
{
    public function __construct(
        private readonly NoOrmTaskCatalog $catalog,
    ) {
    }

    /**
     * @param list<string> $with
     */
    public function paginate(int $perPage, array $with, int $page): PaginatedResult
    {
        return new PaginatedResult(
            $this->catalog->getList($perPage, $with),
            NoOrmTaskCatalog::PAGINATION_TOTAL,
            $page,
            $perPage,
        );
    }

    /**
     * @param list<string> $with
     */
    public function find(int $id, array $with): Task
    {
        return $this->catalog->getSingle($id, $with);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Task
    {
        return $this->catalog->cloneCreateResponse($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Task $task, array $data): Task
    {
        unset($task);

        return $this->catalog->cloneUpdateResponse($data);
    }

    public function delete(Task $task): void
    {
        unset($task);
    }

    public function deleteById(int $id): void
    {
        unset($id);
    }
}

<?php

namespace App\Benchmark\NoOrm;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class NoOrmTaskService
{
    public function __construct(
        private readonly NoOrmTaskCatalog $catalog,
    ) {}

    /**
     * @param list<string> $with
     *
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(int $perPage, array $with, int $page, string $path, array $query): Paginator
    {
        return new Paginator(
            $this->catalog->getList($perPage, $with),
            NoOrmTaskCatalog::PAGINATION_TOTAL,
            $perPage,
            $page,
            [
                'path' => $path,
                'query' => $query,
            ],
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

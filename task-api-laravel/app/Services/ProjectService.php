<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectService
{
    /**
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginate(
        int $perPage,
        array $with,
        ?string $status,
        ?int $userId,
    ): LengthAwarePaginator {
        return Project::query()
            ->with($with)
            ->status($status)
            ->forUser($userId)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $with
     */
    public function find(int $id, array $with): Project
    {
        return Project::query()
            ->with($with)
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return Project::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project
    {
        $project->fill($data);
        $project->save();

        return $project->refresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}

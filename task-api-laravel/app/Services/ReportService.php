<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Zwraca liczbę zadań przypisaną do każdego projektu.
     * Metoda wykorzystywana podczas testów wydajności.
     *
     * @return Collection<int, object{id: int, name: string, user_id: int, status: string, tasks_count: int}>
     */
    public function tasksPerProject(): Collection
    {
        return Project::query()
            ->select(['projects.id', 'projects.name', 'projects.user_id', 'projects.status'])
            ->withCount('tasks')
            ->orderByDesc('tasks_count')
            ->get()
            ->map(fn (Project $p) => (object) [
                'id' => $p->id,
                'name' => $p->name,
                'user_id' => $p->user_id,
                'status' => $p->status,
                'tasks_count' => (int) $p->tasks_count,
            ]);
    }

    /**
     * Zwraca projekty z największą liczbą zadań.
     * Zapytanie wykorzystuje JOIN oraz GROUP BY i służy do testów wydajności.
     *
     * @return Collection<int, object{project_id: int, name: string, tasks_count: int}>
     */
    public function topProjects(int $limit = 10): Collection
    {
        return collect(
            DB::table('projects')
                ->join('tasks', 'tasks.project_id', '=', 'projects.id')
                ->selectRaw('projects.id as project_id, projects.name, COUNT(tasks.id) as tasks_count')
                ->groupBy('projects.id', 'projects.name')
                ->orderByDesc('tasks_count')
                ->limit($limit)
                ->get()
                ->all()
        );
    }

    /**
     * Pobiera zadania wraz z powiązanym projektem, użytkownikiem
     * oraz liczbą tagów i komentarzy. Wykorzystywane podczas testów wydajności.
     *
     * @return Collection<int, Task>
     */
    public function complexTaskOverview(int $limit = 50): Collection
    {
        return Task::query()
            ->select('tasks.*')
            ->with(['project.user'])
            ->withCount(['tags', 'comments'])
            ->orderByDesc('tasks.id')
            ->limit($limit)
            ->get();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ParsesApiIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    use ParsesApiIncludes;

    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function index(IndexTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $with = $this->allowedIncludes($request, ['project', 'comments', 'tags']);

        $paginator = $this->tasks->paginate(
            $perPage,
            $with,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            $validated['status'] ?? null,
            $validated['priority'] ?? null,
        );

        return TaskResource::collection($paginator)->response();
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->validated());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Task $task): JsonResponse
    {
        $with = $this->allowedIncludes(request(), ['project', 'comments', 'tags']);

        if ($with !== []) {
            $task = $this->tasks->find($task->id, $with);
        }

        return (new TaskResource($task))->response();
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $updated = $this->tasks->update($task, $request->validated());

        return (new TaskResource($updated))->response();
    }

    public function destroy(Task $task): Response
    {
        $this->tasks->delete($task);

        return response()->noContent();
    }
}

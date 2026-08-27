<?php

namespace App\Http\Controllers\Api\Benchmark;

use App\Benchmark\NoOrm\ModelPreventLazy;
use App\Benchmark\NoOrm\NoOrmTaskService;
use App\Http\Concerns\ParsesApiIncludes;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NoOrmTaskController extends Controller
{
    use ParsesApiIncludes;

    public function __construct(
        private readonly NoOrmTaskService $tasks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        ModelPreventLazy::enable();

        $v = Validator::make($request->query(), [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'with' => ['sometimes', 'string'],
            'include' => ['sometimes', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $v->errors(),
            ], 422);
        }

        $perPage = (int) ($request->query('per_page') ?? 15);
        $page = max(1, (int) $request->query('page', 1));
        $with = $this->allowedIncludes($request, ['project', 'comments', 'tags']);

        $paginator = $this->tasks->paginate(
            $perPage,
            $with,
            $page,
            $request->url(),
            $request->query(),
        );

        return TaskResource::collection($paginator)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        ModelPreventLazy::enable();

        $with = $this->allowedIncludes($request, ['project', 'comments', 'tags']);

        return (new TaskResource($this->tasks->find($id, $with)))->response();
    }

    public function store(Request $request): JsonResponse
    {
        ModelPreventLazy::enable();

        $v = Validator::make($request->all(), [
            'project_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in([
                Task::STATUS_TODO,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_DONE,
                Task::STATUS_CANCELLED,
            ])],
            'priority' => ['sometimes', 'string', Rule::in([
                Task::PRIORITY_LOW,
                Task::PRIORITY_MEDIUM,
                Task::PRIORITY_HIGH,
                Task::PRIORITY_URGENT,
            ])],
            'due_date' => ['nullable', 'date'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'min:1'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $v->errors(),
            ], 422);
        }

        return (new TaskResource($this->tasks->create($v->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        ModelPreventLazy::enable();

        $v = Validator::make($request->all(), [
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in([
                Task::STATUS_TODO,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_DONE,
                Task::STATUS_CANCELLED,
            ])],
            'priority' => ['sometimes', 'string', Rule::in([
                Task::PRIORITY_LOW,
                Task::PRIORITY_MEDIUM,
                Task::PRIORITY_HIGH,
                Task::PRIORITY_URGENT,
            ])],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'min:1'],
        ]);
        if ($v->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $v->errors(),
            ], 422);
        }

        $base = $this->tasks->find($id, []);

        return (new TaskResource($this->tasks->update($base, $v->validated())))->response();
    }

    public function destroy(int $id): Response
    {
        ModelPreventLazy::enable();
        $this->tasks->deleteById($id);

        return response()->noContent();
    }
}

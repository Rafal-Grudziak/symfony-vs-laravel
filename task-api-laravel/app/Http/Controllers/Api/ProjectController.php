<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ParsesApiIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\IndexProjectRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    use ParsesApiIncludes;

    public function __construct(
        private readonly ProjectService $projects,
    ) {}

    public function index(IndexProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $with = $this->allowedIncludes($request, ['user', 'tasks']);

        $paginator = $this->projects->paginate(
            $perPage,
            $with,
            $validated['status'] ?? null,
            isset($validated['user_id']) ? (int) $validated['user_id'] : null,
        );

        return ProjectResource::collection($paginator)->response();
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projects->create($request->validated());

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project): JsonResponse
    {
        $with = $this->allowedIncludes(request(), ['user', 'tasks']);

        return (new ProjectResource($this->projects->find($project->id, $with)))->response();
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $updated = $this->projects->update($project, $request->validated());

        return (new ProjectResource($updated))->response();
    }

    public function destroy(Project $project): Response
    {
        $this->projects->delete($project);

        return response()->noContent();
    }
}

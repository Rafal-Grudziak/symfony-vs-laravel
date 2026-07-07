<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ParsesApiIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\IndexCommentRequest;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    use ParsesApiIncludes;

    public function __construct(
        private readonly CommentService $comments,
    ) {}

    public function index(IndexCommentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $with = $this->allowedIncludes($request, ['task']);

        $paginator = $this->comments->paginate(
            $perPage,
            $with,
            isset($validated['task_id']) ? (int) $validated['task_id'] : null,
        );

        return CommentResource::collection($paginator)->response();
    }

    public function storeForTask(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $this->comments->createForTask($task, $request->validated('content'));

        return (new CommentResource($comment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Comment $comment): JsonResponse
    {
        $with = $this->allowedIncludes(request(), ['task']);

        return (new CommentResource($this->comments->find($comment->id, $with)))->response();
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $updated = $this->comments->update($comment, $request->validated());

        return (new CommentResource($updated))->response();
    }

    public function destroy(Comment $comment): Response
    {
        $this->comments->delete($comment);

        return response()->noContent();
    }
}

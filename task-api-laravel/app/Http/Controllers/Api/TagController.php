<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ParsesApiIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\IndexTagRequest;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TagController extends Controller
{
    use ParsesApiIncludes;

    public function __construct(
        private readonly TagService $tags,
    ) {}

    public function index(IndexTagRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $with = $this->allowedIncludes($request, ['tasks']);

        $paginator = $this->tags->paginate($perPage, $with);

        return TagResource::collection($paginator)->response();
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $this->tags->create($request->validated());

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tag $tag): JsonResponse
    {
        $with = $this->allowedIncludes(request(), ['tasks']);

        return (new TagResource($this->tags->find($tag->id, $with)))->response();
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $updated = $this->tags->update($tag, $request->validated());

        return (new TagResource($updated))->response();
    }

    public function destroy(Tag $tag): Response
    {
        $this->tags->delete($tag);

        return response()->noContent();
    }
}

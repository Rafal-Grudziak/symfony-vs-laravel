<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\IncludeParser;
use App\Api\ResourceSerializer;
use App\Dto\CommentStoreBody;
use App\Dto\CommentUpdateBody;
use App\Entity\Comment;
use App\Entity\Task;
use App\Http\ApiValidation;
use App\Pagination\LaravelStylePaginationBuilder;
use App\Service\CommentService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class CommentController
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly ResourceSerializer $serializer,
        private readonly LaravelStylePaginationBuilder $paginationBuilder,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/comments', name: 'api_comments_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $v = $this->validator->validate($request->query->all(), $this->indexConstraints());
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $perPage = (int) ($request->query->get('per_page') ?? 15);
        $page = max(1, (int) $request->query->get('page', 1));
        $with = IncludeParser::allowed($request, ['task']);
        $taskId = $request->query->has('task_id') && $request->query->get('task_id') !== '' && $request->query->get('task_id') !== null
            ? (int) $request->query->get('task_id')
            : null;

        $paginated = $this->comments->paginate($perPage, $with, $taskId, $page);
        $data = array_map(fn (Comment $c) => $this->serializer->commentToArray($c), $paginated->items);

        return new JsonResponse($this->paginationBuilder->buildWrappedCollection($request, $paginated, $data));
    }

    #[Route('/tasks/{id}/comments', name: 'api_comments_store_for_task', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function storeForTask(#[MapEntity(id: 'id')] Task $task, #[MapRequestPayload] CommentStoreBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $c = $this->comments->createForTask($task, $body->content);

        return new JsonResponse(['data' => $this->serializer->commentToArray($c)], Response::HTTP_CREATED);
    }

    #[Route('/comments/{id}', name: 'api_comments_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, #[MapEntity(id: 'id')] Comment $comment): JsonResponse
    {
        $with = IncludeParser::allowed($request, ['task']);
        $fresh = $this->comments->find((int) $comment->getId(), $with);

        return new JsonResponse(['data' => $this->serializer->commentToArray($fresh)]);
    }

    #[Route('/comments/{id}', name: 'api_comments_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'id')] Comment $comment, #[MapRequestPayload] CommentUpdateBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        /** @var array<string, mixed> $raw */
        $raw = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($raw)) {
            $raw = [];
        }

        $data = [];
        if (array_key_exists('content', $raw)) {
            $data['content'] = (string) $body->content;
        }

        $updated = $this->comments->update($comment, $data);

        return new JsonResponse(['data' => $this->serializer->commentToArray($updated)]);
    }

    #[Route('/comments/{id}', name: 'api_comments_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(#[MapEntity(id: 'id')] Comment $comment): Response
    {
        $this->comments->delete($comment);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function indexConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'per_page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Range(min: 1, max: 100)])],
            'page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'task_id' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'with' => new Assert\Optional([new Assert\Type('string')]),
            'include' => new Assert\Optional([new Assert\Type('string')]),
        ], allowExtraFields: true, allowMissingFields: true);
    }
}

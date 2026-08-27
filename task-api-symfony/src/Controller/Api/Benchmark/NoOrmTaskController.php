<?php

declare(strict_types=1);

namespace App\Controller\Api\Benchmark;

use App\Api\IncludeParser;
use App\Api\ResourceSerializer;
use App\Benchmark\NoOrm\NoOrmTaskService;
use App\Dto\TaskStoreBody;
use App\Dto\TaskUpdateBody;
use App\Entity\Task;
use App\Http\ApiValidation;
use App\Pagination\LaravelStylePaginationBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;


#[Route('/api/benchmark/no-orm/tasks')]
final class NoOrmTaskController
{
    public function __construct(
        private readonly NoOrmTaskService $tasks,
        private readonly ResourceSerializer $serializer,
        private readonly LaravelStylePaginationBuilder $paginationBuilder,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_benchmark_no_orm_tasks_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $v = $this->validator->validate($request->query->all(), new Assert\Collection([
            'per_page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Range(min: 1, max: 100)])],
            'page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'with' => new Assert\Optional([new Assert\Type('string')]),
            'include' => new Assert\Optional([new Assert\Type('string')]),
        ], allowExtraFields: true, allowMissingFields: true));
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $perPage = (int) ($request->query->get('per_page') ?? 15);
        $page = max(1, (int) $request->query->get('page', 1));
        $with = IncludeParser::allowed($request, ['project', 'comments', 'tags']);

        $paginated = $this->tasks->paginate($perPage, $with, $page);
        $data = array_map(fn (Task $t) => $this->serializer->taskToArray($t), $paginated->items);

        return new JsonResponse($this->paginationBuilder->buildWrappedCollection($request, $paginated, $data));
    }

    #[Route('/{id}', name: 'api_benchmark_no_orm_tasks_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): JsonResponse
    {
        $with = IncludeParser::allowed($request, ['project', 'comments', 'tags']);

        return new JsonResponse(['data' => $this->serializer->taskToArray($this->tasks->find($id, $with))]);
    }

    #[Route('', name: 'api_benchmark_no_orm_tasks_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] TaskStoreBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $data = [
            'project_id' => $body->projectId,
            'title' => $body->title,
            'description' => $body->description,
            'status' => $body->status,
            'priority' => $body->priority,
            'due_date' => $body->dueDate,
            'tag_ids' => $body->tagIds,
        ];

        return new JsonResponse(
            ['data' => $this->serializer->taskToArray($this->tasks->create($data))],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'api_benchmark_no_orm_tasks_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, int $id, #[MapRequestPayload] TaskUpdateBody $body): JsonResponse
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
        if (array_key_exists('title', $raw) && $body->title !== null) {
            $data['title'] = $body->title;
        }
        if (array_key_exists('description', $raw)) {
            $data['description'] = $body->description;
        }
        if (array_key_exists('status', $raw) && $body->status !== null) {
            $data['status'] = $body->status;
        }
        if (array_key_exists('priority', $raw) && $body->priority !== null) {
            $data['priority'] = $body->priority;
        }

        $base = $this->tasks->find($id, []);

        return new JsonResponse(['data' => $this->serializer->taskToArray($this->tasks->update($base, $data))]);
    }

    #[Route('/{id}', name: 'api_benchmark_no_orm_tasks_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(int $id): Response
    {
        $this->tasks->deleteById($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}

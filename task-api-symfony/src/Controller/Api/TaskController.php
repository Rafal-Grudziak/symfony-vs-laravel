<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\IncludeParser;
use App\Api\ResourceSerializer;
use App\Dto\TaskStoreBody;
use App\Dto\TaskUpdateBody;
use App\Entity\Task;
use App\Http\ApiValidation;
use App\Pagination\LaravelStylePaginationBuilder;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Service\TaskService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tasks')]
final class TaskController
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ResourceSerializer $serializer,
        private readonly LaravelStylePaginationBuilder $paginationBuilder,
        private readonly ValidatorInterface $validator,
        private readonly ProjectRepository $projects,
        private readonly TagRepository $tags,
    ) {
    }

    #[Route('', name: 'api_tasks_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $v = $this->validator->validate($request->query->all(), $this->indexConstraints());
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $perPage = (int) ($request->query->get('per_page') ?? 15);
        $page = max(1, (int) $request->query->get('page', 1));
        $with = IncludeParser::allowed($request, ['project', 'comments', 'tags']);
        $projectId = $request->query->has('project_id') && $request->query->get('project_id') !== '' && $request->query->get('project_id') !== null
            ? (int) $request->query->get('project_id')
            : null;
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');

        $paginated = $this->tasks->paginate(
            $perPage,
            $with,
            $projectId,
            is_string($status) ? $status : null,
            is_string($priority) ? $priority : null,
            $page,
        );
        $data = array_map(fn (Task $t) => $this->serializer->taskToArray($t), $paginated->items);

        return new JsonResponse($this->paginationBuilder->buildWrappedCollection($request, $paginated, $data));
    }

    #[Route('', name: 'api_tasks_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] TaskStoreBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        if ($this->projects->find($body->projectId) === null) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['project_id' => ['The selected project id is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (is_array($body->tagIds)) {
            foreach ($body->tagIds as $tid) {
                if ($this->tags->find((int) $tid) === null) {
                    return new JsonResponse([
                        'message' => 'The given data was invalid.',
                        'errors' => ['tag_ids' => ['One or more tag ids are invalid.']],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        $data = [
            'project_id' => $body->projectId,
            'title' => $body->title,
            'description' => $body->description,
            'due_date' => $body->dueDate,
            'tag_ids' => $body->tagIds,
        ];
        if ($body->status !== null) {
            $data['status'] = $body->status;
        }
        if ($body->priority !== null) {
            $data['priority'] = $body->priority;
        }

        $task = $this->tasks->create($data);

        return new JsonResponse(['data' => $this->serializer->taskToArray($task)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_tasks_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, #[MapEntity(id: 'id')] Task $task): JsonResponse
    {
        $with = IncludeParser::allowed($request, ['project', 'comments', 'tags']);

        if ($with !== []) {
            $task = $this->tasks->find((int) $task->getId(), $with);
        }

        return new JsonResponse(['data' => $this->serializer->taskToArray($task)]);
    }

    #[Route('/{id}', name: 'api_tasks_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'id')] Task $task, #[MapRequestPayload] TaskUpdateBody $body): JsonResponse
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

        if ($body->projectId !== null && $this->projects->find($body->projectId) === null) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['project_id' => ['The selected project id is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('tag_ids', $raw) && is_array($body->tagIds)) {
            foreach ($body->tagIds as $tid) {
                if ($this->tags->find((int) $tid) === null) {
                    return new JsonResponse([
                        'message' => 'The given data was invalid.',
                        'errors' => ['tag_ids' => ['One or more tag ids are invalid.']],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        $data = [];
        if (array_key_exists('project_id', $raw) && $body->projectId !== null) {
            $data['project_id'] = $body->projectId;
        }
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
        if (array_key_exists('due_date', $raw)) {
            $data['due_date'] = $body->dueDate;
        }
        if (array_key_exists('tag_ids', $raw)) {
            $data['tag_ids'] = $body->tagIds;
        }

        $updated = $this->tasks->update($task, $data);

        return new JsonResponse(['data' => $this->serializer->taskToArray($updated)]);
    }

    #[Route('/{id}', name: 'api_tasks_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(#[MapEntity(id: 'id')] Task $task): Response
    {
        $this->tasks->delete($task);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function indexConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'per_page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Range(min: 1, max: 100)])],
            'page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'project_id' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'status' => [new Assert\Optional([new Assert\Choice(choices: [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_DONE, Task::STATUS_CANCELLED, ''])])],
            'priority' => [new Assert\Optional([new Assert\Choice(choices: [Task::PRIORITY_LOW, Task::PRIORITY_MEDIUM, Task::PRIORITY_HIGH, Task::PRIORITY_URGENT, ''])])],
            'with' => new Assert\Optional([new Assert\Type('string')]),
            'include' => new Assert\Optional([new Assert\Type('string')]),
        ], allowExtraFields: true, allowMissingFields: true);
    }
}

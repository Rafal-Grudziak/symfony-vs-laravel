<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\IncludeParser;
use App\Api\ResourceSerializer;
use App\Dto\ProjectStoreBody;
use App\Dto\ProjectUpdateBody;
use App\Entity\Project;
use App\Http\ApiValidation;
use App\Pagination\LaravelStylePaginationBuilder;
use App\Repository\UserRepository;
use App\Service\ProjectService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/projects')]
final class ProjectController
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ResourceSerializer $serializer,
        private readonly LaravelStylePaginationBuilder $paginationBuilder,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'api_projects_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $v = $this->validator->validate($request->query->all(), $this->indexConstraints());
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $perPage = (int) ($request->query->get('per_page') ?? 15);
        $page = max(1, (int) $request->query->get('page', 1));
        $with = IncludeParser::allowed($request, ['user', 'tasks']);
        $status = $request->query->get('status');
        $userId = $request->query->has('user_id') && $request->query->get('user_id') !== '' && $request->query->get('user_id') !== null
            ? (int) $request->query->get('user_id')
            : null;

        $paginated = $this->projects->paginate($perPage, $with, is_string($status) ? $status : null, $userId, $page);
        $data = array_map(fn (Project $p) => $this->serializer->projectToArray($p), $paginated->items);

        return new JsonResponse($this->paginationBuilder->buildWrappedCollection($request, $paginated, $data));
    }

    #[Route('', name: 'api_projects_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] ProjectStoreBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        if (!$this->users->existsById($body->userId)) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['user_id' => ['The selected user id is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = [
            'user_id' => $body->userId,
            'name' => $body->name,
            'description' => $body->description,
        ];
        if ($body->status !== null) {
            $data['status'] = $body->status;
        }

        $p = $this->projects->create($data);

        return new JsonResponse(['data' => $this->serializer->projectToArray($p)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_projects_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): JsonResponse
    {
        $with = IncludeParser::allowed($request, ['user', 'tasks']);
        $p = $this->projects->find($id, $with);

        return new JsonResponse(['data' => $this->serializer->projectToArray($p)]);
    }

    #[Route('/{id}', name: 'api_projects_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, int $id, #[MapRequestPayload] ProjectUpdateBody $body): JsonResponse
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

        if ($body->userId !== null && !$this->users->existsById($body->userId)) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['user_id' => ['The selected user id is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $p = $this->projects->find($id, []);
        $data = [];
        if (array_key_exists('user_id', $raw) && $body->userId !== null) {
            $data['user_id'] = $body->userId;
        }
        if (array_key_exists('name', $raw) && $body->name !== null) {
            $data['name'] = $body->name;
        }
        if (array_key_exists('description', $raw)) {
            $data['description'] = $body->description;
        }
        if (array_key_exists('status', $raw) && $body->status !== null) {
            $data['status'] = $body->status;
        }

        $updated = $this->projects->update($p, $data);

        return new JsonResponse(['data' => $this->serializer->projectToArray($updated)]);
    }

    #[Route('/{id}', name: 'api_projects_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(int $id): Response
    {
        $p = $this->projects->find($id, []);
        $this->projects->delete($p);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function indexConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'per_page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Range(min: 1, max: 100)])],
            'page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'status' => [new Assert\Optional([new Assert\Choice(choices: [Project::STATUS_DRAFT, Project::STATUS_ACTIVE, Project::STATUS_ARCHIVED, ''])])],
            'user_id' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'with' => new Assert\Optional([new Assert\Type('string')]),
            'include' => new Assert\Optional([new Assert\Type('string')]),
        ], allowExtraFields: true, allowMissingFields: true);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\IncludeParser;
use App\Api\ResourceSerializer;
use App\Dto\TagStoreBody;
use App\Dto\TagUpdateBody;
use App\Entity\Tag;
use App\Http\ApiValidation;
use App\Pagination\LaravelStylePaginationBuilder;
use App\Repository\TagRepository;
use App\Service\TagService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tags')]
final class TagController
{
    public function __construct(
        private readonly TagService $tags,
        private readonly ResourceSerializer $serializer,
        private readonly LaravelStylePaginationBuilder $paginationBuilder,
        private readonly ValidatorInterface $validator,
        private readonly TagRepository $tagRepo,
    ) {
    }

    #[Route('', name: 'api_tags_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $v = $this->validator->validate($request->query->all(), $this->indexConstraints());
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        $perPage = (int) ($request->query->get('per_page') ?? 15);
        $page = max(1, (int) $request->query->get('page', 1));
        $with = IncludeParser::allowed($request, ['tasks']);

        $paginated = $this->tags->paginate($perPage, $with, $page);
        $data = array_map(fn (Tag $g) => $this->serializer->tagToArray($g), $paginated->items);

        return new JsonResponse($this->paginationBuilder->buildWrappedCollection($request, $paginated, $data));
    }

    #[Route('', name: 'api_tags_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] TagStoreBody $body): JsonResponse
    {
        $v = $this->validator->validate($body);
        if (count($v) > 0) {
            return ApiValidation::violationResponse($v);
        }

        if ($this->tagRepo->findOneBy(['name' => $body->name]) !== null) {
            return new JsonResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['The name has already been taken.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = ['name' => $body->name];
        if ($body->color !== null) {
            $data['color'] = $body->color;
        }

        $t = $this->tags->create($data);

        return new JsonResponse(['data' => $this->serializer->tagToArray($t)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_tags_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, #[MapEntity(id: 'id')] Tag $tag): JsonResponse
    {
        $with = IncludeParser::allowed($request, ['tasks']);
        $fresh = $this->tags->find((int) $tag->getId(), $with);

        return new JsonResponse(['data' => $this->serializer->tagToArray($fresh)]);
    }

    #[Route('/{id}', name: 'api_tags_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, #[MapEntity(id: 'id')] Tag $tag, #[MapRequestPayload] TagUpdateBody $body): JsonResponse
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

        if (array_key_exists('name', $raw) && $body->name !== null) {
            if ($this->tagRepo->existsOtherWithName($body->name, (int) $tag->getId())) {
                return new JsonResponse([
                    'message' => 'The given data was invalid.',
                    'errors' => ['name' => ['The name has already been taken.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $data = [];
        if (array_key_exists('name', $raw) && $body->name !== null) {
            $data['name'] = $body->name;
        }
        if (array_key_exists('color', $raw) && $body->color !== null) {
            $data['color'] = $body->color;
        }

        $updated = $this->tags->update($tag, $data);

        return new JsonResponse(['data' => $this->serializer->tagToArray($updated)]);
    }

    #[Route('/{id}', name: 'api_tags_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(#[MapEntity(id: 'id')] Tag $tag): Response
    {
        $this->tags->delete($tag);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function indexConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'per_page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Range(min: 1, max: 100)])],
            'page' => [new Assert\Optional([new Assert\Type('numeric'), new Assert\Positive()])],
            'with' => new Assert\Optional([new Assert\Type('string')]),
            'include' => new Assert\Optional([new Assert\Type('string')]),
        ], allowExtraFields: true, allowMissingFields: true);
    }
}

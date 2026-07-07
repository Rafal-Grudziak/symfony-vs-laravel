<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Pagination\PaginatedResult;
use App\Pagination\PaginationFactory;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TagService
{
    public function __construct(
        private readonly TagRepository $tags,
        private readonly PaginationFactory $pagination,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<string> $with
     */
    public function paginate(int $perPage, array $with, int $page): PaginatedResult
    {
        $qb = $this->tags->createListingQueryBuilder($with);

        return $this->pagination->paginate($qb->getQuery(), $page, $perPage);
    }

    /**
     * @param list<string> $with
     */
    public function find(int $id, array $with): Tag
    {
        $qb = $this->tags->createListingQueryBuilder($with)
            ->andWhere('g.id = :id')
            ->setParameter('id', $id);

        /** @var Tag|null $t */
        $t = $qb->getQuery()->getOneOrNullResult();
        if ($t === null) {
            throw new NotFoundHttpException(sprintf('No query results for model [Tag] %d', $id));
        }

        return $t;
    }

    /**
     * @param array{name: string, color?: string} $data
     */
    public function create(array $data): Tag
    {
        $t = new Tag();
        $t->setName($data['name']);
        if (isset($data['color'])) {
            $t->setColor((string) $data['color']);
        }
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Tag $tag, array $data): Tag
    {
        if (array_key_exists('name', $data)) {
            $tag->setName((string) $data['name']);
        }
        if (array_key_exists('color', $data)) {
            $tag->setColor((string) $data['color']);
        }
        $this->em->flush();

        return $tag;
    }

    public function delete(Tag $tag): void
    {
        $this->em->remove($tag);
        $this->em->flush();
    }
}

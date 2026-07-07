<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Pagination\PaginatedResult;
use App\Pagination\PaginationFactory;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly PaginationFactory $pagination,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<string> $with
     */
    public function paginate(int $perPage, array $with, ?string $status, ?int $userId, int $page): PaginatedResult
    {
        $qb = $this->projects->createListingQueryBuilder($with, $status, $userId);

        return $this->pagination->paginate($qb->getQuery(), $page, $perPage);
    }

    /**
     * @param list<string> $with
     */
    public function find(int $id, array $with): Project
    {
        $qb = $this->projects->createListingQueryBuilder($with, null, null)
            ->andWhere('p.id = :id')
            ->setParameter('id', $id);

        /** @var Project|null $p */
        $p = $qb->getQuery()->getOneOrNullResult();
        if ($p === null) {
            throw $this->notFound('Project', $id);
        }

        return $p;
    }

    /**
     * @param array{user_id: int, name: string, description?: ?string, status?: string} $data
     */
    public function create(array $data): Project
    {
        $p = new Project();
        $user = $this->em->getReference(\App\Entity\User::class, $data['user_id']);
        $p->setUser($user);
        $p->setName($data['name']);
        $p->setDescription($data['description'] ?? null);
        if (isset($data['status'])) {
            $p->setStatus($data['status']);
        }

        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Project $project, array $data): Project
    {
        if (array_key_exists('user_id', $data)) {
            $project->setUser($this->em->getReference(\App\Entity\User::class, (int) $data['user_id']));
        }
        if (array_key_exists('name', $data)) {
            $project->setName((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $project->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }
        if (array_key_exists('status', $data)) {
            $project->setStatus((string) $data['status']);
        }

        $this->em->flush();

        return $project;
    }

    public function delete(Project $project): void
    {
        $this->em->remove($project);
        $this->em->flush();
    }

    private function notFound(string $entity, int $id): \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
    {
        return new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(sprintf('No query results for model [%s] %d', $entity, $id));
    }
}

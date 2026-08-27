<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\TaskTag;
use App\Pagination\PaginatedResult;
use App\Pagination\PaginationFactory;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly PaginationFactory $pagination,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<string> $with
     */
    public function paginate(int $perPage, array $with, ?int $projectId, ?string $status, ?string $priority, int $page): PaginatedResult
    {
        $qb = $this->tasks->createListingQueryBuilder($with, $projectId, $status, $priority);

        return $this->pagination->paginate($qb->getQuery(), $page, $perPage);
    }

    /**
     * @param list<string> $with
     */
    public function find(int $id, array $with): Task
    {
        $qb = $this->tasks->createShowQueryBuilder($id, $with);
        /** @var Task|null $t */
        $t = $qb->getQuery()->getOneOrNullResult();
        if ($t === null) {
            throw new NotFoundHttpException(sprintf('No query results for model [Task] %d', $id));
        }

        return $t;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Task
    {
        /** @var list<int>|null $tagIds */
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $task = new Task();
        $task->setProject($this->em->getReference(\App\Entity\Project::class, (int) $data['project_id']));
        $task->setTitle((string) $data['title']);
        $task->setDescription($data['description'] ?? null);
        if (isset($data['status'])) {
            $task->setStatus((string) $data['status']);
        }
        if (isset($data['priority'])) {
            $task->setPriority((string) $data['priority']);
        }
        if (array_key_exists('due_date', $data)) {
            $task->setDueDate($this->parseDueDate($data['due_date']));
        }

        $this->em->persist($task);
        $this->em->flush();

        if (is_array($tagIds) && $tagIds !== []) {
            $this->syncTags($task, $tagIds);
            $this->em->flush();
        }

        return $this->find((int) $task->getId(), ['tags']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Task $task, array $data): Task
    {
        $hasTagIds = array_key_exists('tag_ids', $data);
        $tagIds = $hasTagIds ? $data['tag_ids'] : null;
        unset($data['tag_ids']);

        if ($hasTagIds && is_array($tagIds)) {
            $this->syncTags($task, $tagIds);
        }

        if (array_key_exists('project_id', $data)) {
            $task->setProject($this->em->getReference(\App\Entity\Project::class, (int) $data['project_id']));
        }
        if (array_key_exists('title', $data)) {
            $task->setTitle((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $task->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }
        if (array_key_exists('status', $data)) {
            $task->setStatus((string) $data['status']);
        }
        if (array_key_exists('priority', $data)) {
            $task->setPriority((string) $data['priority']);
        }
        if (array_key_exists('due_date', $data)) {
            $task->setDueDate($this->parseDueDate($data['due_date']));
        }

        $this->em->flush();

        return $this->find((int) $task->getId(), ['tags']);
    }

    public function delete(Task $task): void
    {
        $this->em->remove($task);
        $this->em->flush();
    }

    /**
     * @param list<int> $tagIds
     */
    private function syncTags(Task $task, array $tagIds): void
    {
        foreach ($task->getTaskTags()->toArray() as $existing) {
            $task->getTaskTags()->removeElement($existing);
            $this->em->remove($existing);
        }

        foreach ($tagIds as $tid) {
            $tag = $this->em->find(Tag::class, (int) $tid);
            if ($tag === null) {
                continue;
            }
            $link = new TaskTag();
            $link->setTask($task);
            $link->setTag($tag);
            $this->em->persist($link);
            $task->addTaskTag($link);
        }
    }

    private function parseDueDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        return new \DateTimeImmutable((string) $value);
    }
}

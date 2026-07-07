<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Task;
use App\Pagination\PaginatedResult;
use App\Pagination\PaginationFactory;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CommentService
{
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly PaginationFactory $pagination,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<string> $with
     */
    public function paginate(int $perPage, array $with, ?int $taskId, int $page): PaginatedResult
    {
        $qb = $this->comments->createListingQueryBuilder($with, $taskId);

        return $this->pagination->paginate($qb->getQuery(), $page, $perPage);
    }

    /**
     * @param list<string> $with
     */
    public function find(int $id, array $with): Comment
    {
        $qb = $this->comments->createListingQueryBuilder($with, null)
            ->andWhere('c.id = :id')
            ->setParameter('id', $id);

        /** @var Comment|null $c */
        $c = $qb->getQuery()->getOneOrNullResult();
        if ($c === null) {
            throw new NotFoundHttpException(sprintf('No query results for model [Comment] %d', $id));
        }

        return $c;
    }

    public function createForTask(Task $task, string $content): Comment
    {
        $c = new Comment();
        $c->setTask($task);
        $c->setContent($content);
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    /**
     * @param array{content?: string} $data
     */
    public function update(Comment $comment, array $data): Comment
    {
        if (array_key_exists('content', $data)) {
            $comment->setContent((string) $data['content']);
        }
        $this->em->flush();

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }
}

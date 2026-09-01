<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\TaskTag;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;

final class ResourceSerializer
{
    public function projectToArray(Project $p): array
    {
        $out = [
            'id' => $p->getId(),
            'user_id' => $p->getUser()?->getId(),
            'name' => $p->getName(),
            'description' => $p->getDescription(),
            'status' => $p->getStatus(),
            'created_at' => $this->iso($p->getCreatedAt()),
            'updated_at' => $this->iso($p->getUpdatedAt()),
        ];

        if ($this->entityLoaded($p->getUser())) {
            $out['user'] = $this->userToArray($p->getUser());
        }

        if ($this->collectionInitialized($p->getTasks())) {
            $out['tasks'] = array_map(fn (Task $t) => $this->taskToArray($t), $p->getTasks()->toArray());
        }

        return $out;
    }

    public function taskToArray(Task $t): array
    {
        $out = [
            'id' => $t->getId(),
            'project_id' => $t->getProject()?->getId(),
            'title' => $t->getTitle(),
            'description' => $t->getDescription(),
            'status' => $t->getStatus(),
            'priority' => $t->getPriority(),
            'due_date' => $this->dateOnly($t->getDueDate()),
            'created_at' => $this->iso($t->getCreatedAt()),
            'updated_at' => $this->iso($t->getUpdatedAt()),
        ];

        if ($this->entityLoaded($t->getProject()) && !$this->collectionInitialized($t->getProject()->getTasks())) {
            $out['project'] = $this->projectToArray($t->getProject());
        }

        if ($this->collectionInitialized($t->getComments())) {
            $out['comments'] = array_map(fn (Comment $c) => $this->commentToArray($c), $t->getComments()->toArray());
        }

        if ($this->collectionInitialized($t->getTaskTags())) {
            $tags = [];
            foreach ($t->getTaskTags() as $tt) {
                if ($tt->getTag() !== null) {
                    $tags[] = $this->tagToArray($tt->getTag());
                }
            }
            $out['tags'] = $tags;
        }

        return $out;
    }

    public function commentToArray(Comment $c): array
    {
        $out = [
            'id' => $c->getId(),
            'task_id' => $c->getTask()?->getId(),
            'content' => $c->getContent(),
            'created_at' => $this->iso($c->getCreatedAt()),
            'updated_at' => $this->iso($c->getUpdatedAt()),
        ];

        if ($this->entityLoaded($c->getTask()) && !$this->collectionInitialized($c->getTask()->getComments())) {
            $out['task'] = $this->taskToArray($c->getTask());
        }

        return $out;
    }

    public function tagToArray(Tag $g): array
    {
        $out = [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'color' => $g->getColor(),
            'created_at' => $this->iso($g->getCreatedAt()),
            'updated_at' => $this->iso($g->getUpdatedAt()),
        ];

        if ($this->collectionInitialized($g->getTaskTags())) {
            $tasks = [];
            foreach ($g->getTaskTags() as $tt) {
                if ($tt->getTask() !== null) {
                    $tasks[] = $this->taskToArray($tt->getTask());
                }
            }
            $out['tasks'] = $tasks;
        }

        return $out;
    }

    public function userToArray(User $u): array
    {
        return [
            'id' => $u->getId(),
            'name' => $u->getName(),
            'email' => $u->getEmail(),
            'created_at' => $this->iso($u->getCreatedAt()),
            'updated_at' => $this->iso($u->getUpdatedAt()),
        ];
    }

    private function iso(\DateTimeImmutable $d): string
    {
        return $d->format(\DateTimeInterface::ATOM);
    }

    private function dateOnly(?\DateTimeImmutable $d): ?string
    {
        return $d?->format('Y-m-d');
    }

    private function entityLoaded(?object $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        if ($entity instanceof Proxy) {
            return $entity->__isInitialized();
        }

        return true;
    }

    private function collectionInitialized(Collection $collection): bool
    {
        if ($collection instanceof PersistentCollection) {
            return $collection->isInitialized();
        }

        return !$collection->isEmpty();
    }
}

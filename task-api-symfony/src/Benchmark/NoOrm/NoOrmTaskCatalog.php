<?php

declare(strict_types=1);

namespace App\Benchmark\NoOrm;

use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\TaskTag;
use App\Entity\User;

final class NoOrmTaskCatalog
{
    private const GLOBAL_KEY = 'app.benchmark.no_orm_task_catalog';

    public const COMMENTS_PER_TASK = 5;

    public const TAGS_PER_TASK = 2;

    public const PAGINATION_TOTAL = 10_000;

    /**
     * @return array{
     *     warmed: bool,
     *     lists: array<string, list<Task>>,
     *     singles: array<string, Task>,
     *     createPrototype: ?Task,
     *     updatePrototype: ?Task,
     *     sharedUser: ?User,
     *     sharedTags: list<Tag>
     * }
     */
    private static function &workerState(): array
    {
        if (!isset($GLOBALS[self::GLOBAL_KEY])) {
            $GLOBALS[self::GLOBAL_KEY] = [
                'warmed' => false,
                'lists' => [],
                'singles' => [],
                'createPrototype' => null,
                'updatePrototype' => null,
                'sharedUser' => null,
                'sharedTags' => [],
            ];
            self::tryLoadWorkerCache();
        }

        return $GLOBALS[self::GLOBAL_KEY];
    }

    private static function cacheFile(): string
    {
        return sys_get_temp_dir().'/no_orm_symfony_catalog_'.getmypid().'.ser';
    }

    private static function tryLoadWorkerCache(): void
    {
        $path = self::cacheFile();
        if (!is_readable($path)) {
            return;
        }

        $loaded = @unserialize((string) file_get_contents($path), ['allowed_classes' => true]);
        if (!is_array($loaded) || empty($loaded['warmed'])) {
            return;
        }

        $GLOBALS[self::GLOBAL_KEY] = $loaded;
    }

    /** @param array<string, mixed> $state */
    private static function saveWorkerCache(array $state): void
    {
        file_put_contents(self::cacheFile(), serialize($state), LOCK_EX);
    }

    public function warm(): void
    {
        $state = &self::workerState();
        if ($state['warmed']) {
            return;
        }

        $state['sharedUser'] = new User();
        EntityId::set($state['sharedUser'], 1);
        $state['sharedUser']->setName('NoORM User');
        $state['sharedUser']->setEmail('noorm@example.com');
        $state['sharedUser']->setPassword('unused');

        $state['sharedTags'] = [];
        for ($i = 1; $i <= self::TAGS_PER_TASK; ++$i) {
            $tag = new Tag();
            EntityId::set($tag, $i);
            $tag->setName("noorm-tag-{$i}");
            $tag->setColor(sprintf('#%06X', 0x100000 + $i * 0x111111));
            $state['sharedTags'][] = $tag;
        }

        $bare100 = [];
        $withProject15 = [];
        $withComments15 = [];
        $withTags15 = [];
        $withAll15 = [];
        $withAll100 = [];

        for ($id = 1; $id <= 100; ++$id) {
            $bare100[] = $this->makeBareTask($id);
            if ($id <= 15) {
                $withProject15[] = $this->buildWithRelations($id, ['project']);
                $withComments15[] = $this->buildWithRelations($id, ['comments']);
                $withTags15[] = $this->buildWithRelations($id, ['tags']);
                $withAll15[] = $this->buildWithRelations($id, ['project', 'comments', 'tags']);
            }
            $withAll100[] = $this->buildWithRelations($id, ['project', 'comments', 'tags']);
        }

        $state['lists'] = [
            self::listKey(15, []) => array_slice($bare100, 0, 15),
            self::listKey(100, []) => $bare100,
            self::listKey(15, ['project']) => $withProject15,
            self::listKey(15, ['comments']) => $withComments15,
            self::listKey(15, ['tags']) => $withTags15,
            self::listKey(15, ['project', 'comments', 'tags']) => $withAll15,
            self::listKey(100, ['project', 'comments', 'tags']) => $withAll100,
        ];

        $state['singles'] = [
            self::singleKey(1, []) => $bare100[0],
            self::singleKey(1, ['project', 'comments', 'tags']) => $withAll15[0],
        ];

        $state['createPrototype'] = $this->buildWithRelations(900_001, ['tags']);
        $state['createPrototype']->setTitle('NoORM create prototype');

        $state['updatePrototype'] = $this->buildWithRelations(1, ['tags']);
        $state['updatePrototype']->setTitle('NoORM updated title');
        $state['updatePrototype']->setDescription('Updated');
        $state['updatePrototype']->setStatus(Task::STATUS_DONE);
        $state['updatePrototype']->setPriority(Task::PRIORITY_HIGH);

        $state['warmed'] = true;
        self::saveWorkerCache($state);
    }

    public function isWarmed(): bool
    {
        return self::workerState()['warmed'];
    }

    /**
     * @param list<string> $with
     *
     * @return list<Task>
     */
    public function getList(int $perPage, array $with): array
    {
        $this->assertWarmed();
        $state = self::workerState();

        return $state['lists'][self::listKey($perPage, $with)];
    }

    /**
     * @param list<string> $with
     */
    public function getSingle(int $id, array $with): Task
    {
        $this->assertWarmed();
        $state = self::workerState();
        $key = self::singleKey($id, $with);

        return $state['singles'][$key] ?? $state['singles']['1:'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cloneCreateResponse(array $data): Task
    {
        $this->assertWarmed();
        $task = clone self::workerState()['createPrototype'];
        if (isset($data['title'])) {
            $task->setTitle((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $task->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }
        if (isset($data['status'])) {
            $task->setStatus((string) $data['status']);
        }
        if (isset($data['priority'])) {
            $task->setPriority((string) $data['priority']);
        }

        return $task;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cloneUpdateResponse(array $data): Task
    {
        $this->assertWarmed();
        $task = clone self::workerState()['updatePrototype'];
        if (array_key_exists('title', $data) && $data['title'] !== null) {
            $task->setTitle((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $task->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $task->setStatus((string) $data['status']);
        }
        if (array_key_exists('priority', $data) && $data['priority'] !== null) {
            $task->setPriority((string) $data['priority']);
        }

        return $task;
    }

    /**
     * @param list<string> $with
     */
    private static function listKey(int $perPage, array $with): string
    {
        sort($with);
        $withKey = $with === [] ? '' : implode(',', $with);

        return "{$perPage}:{$withKey}";
    }

    /**
     * @param list<string> $with
     */
    private static function singleKey(int $id, array $with): string
    {
        sort($with);
        $withKey = $with === [] ? '' : implode(',', $with);

        return "{$id}:{$withKey}";
    }

    private function assertWarmed(): void
    {
        if (!self::workerState()['warmed']) {
            $this->warm();
        }
    }

    private function makeBareTask(int $id): Task
    {
        $task = new Task();
        EntityId::set($task, $id);
        $task->setProject($this->makeProject(false));
        $task->setTitle("NoORM Task {$id}");
        $task->setDescription("Task {$id}");
        $task->setStatus(Task::STATUS_TODO);
        $task->setPriority(Task::PRIORITY_MEDIUM);

        return $task;
    }

    /**
     * @param list<string> $with
     */
    private function buildWithRelations(int $id, array $with): Task
    {
        $includeProject = in_array('project', $with, true);
        $task = new Task();
        EntityId::set($task, $id);
        $task->setProject($this->makeProject($includeProject));
        $task->setTitle("NoORM Task {$id}");
        $task->setDescription("Task {$id}");
        $task->setStatus(Task::STATUS_TODO);
        $task->setPriority(Task::PRIORITY_MEDIUM);

        if (in_array('comments', $with, true)) {
            for ($c = 1; $c <= self::COMMENTS_PER_TASK; ++$c) {
                $comment = new Comment();
                EntityId::set($comment, ($id - 1) * self::COMMENTS_PER_TASK + $c);
                $comment->setContent("NoORM comment {$c} on task {$id}");
                $comment->setTask($task);
                $task->getComments()->add($comment);
            }
        }

        if (in_array('tags', $with, true)) {
            foreach (self::workerState()['sharedTags'] as $tag) {
                $link = new TaskTag();
                EntityId::set($link, $id * 10 + (int) $tag->getId());
                $link->setTag($tag);
                $task->addTaskTag($link);
            }
        }

        return $task;
    }

    private function makeProject(bool $includeInJson): Project
    {
        $state = self::workerState();
        $project = new Project();
        EntityId::set($project, 1);
        $project->setUser($state['sharedUser']);
        $project->setName('NoORM Project');
        $project->setDescription(null);
        $project->setStatus(Project::STATUS_ACTIVE);

        if (!$includeInJson) {
            $dummy = new Task();
            EntityId::set($dummy, 0);
            $project->getTasks()->add($dummy);
        }

        return $project;
    }
}

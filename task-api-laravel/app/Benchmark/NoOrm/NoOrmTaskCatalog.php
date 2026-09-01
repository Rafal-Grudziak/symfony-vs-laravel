<?php

namespace App\Benchmark\NoOrm;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     *     updatePrototype: ?Task
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
            ];
            self::tryLoadWorkerCache();
        }

        return $GLOBALS[self::GLOBAL_KEY];
    }

    private static function cacheFile(): string
    {
        return sys_get_temp_dir().'/no_orm_laravel_catalog_'.getmypid().'.ser';
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

        $fixed = Carbon::parse('2024-01-15T12:00:00+00:00');
        $project = $this->makeProject($fixed);
        $tags = $this->makeTags($fixed);

        $bare100 = [];
        $withProject15 = [];
        $withComments15 = [];
        $withTags15 = [];
        $withAll15 = [];
        $withAll100 = [];

        for ($id = 1; $id <= 100; ++$id) {
            $bare100[] = $this->makeBareTask($id, $fixed);
            if ($id <= 15) {
                $withProject15[] = $this->attachRelations(
                    $this->makeBareTask($id, $fixed),
                    project: clone $project,
                );
                $withComments15[] = $this->attachRelations(
                    $this->makeBareTask($id, $fixed),
                    comments: $this->makeComments($id, $fixed),
                );
                $withTags15[] = $this->attachRelations(
                    $this->makeBareTask($id, $fixed),
                    tags: $this->cloneTags($tags),
                );
                $withAll15[] = $this->attachRelations(
                    $this->makeBareTask($id, $fixed),
                    project: clone $project,
                    comments: $this->makeComments($id, $fixed),
                    tags: $this->cloneTags($tags),
                );
            }
            $withAll100[] = $this->attachRelations(
                $this->makeBareTask($id, $fixed),
                project: clone $project,
                comments: $this->makeComments($id, $fixed),
                tags: $this->cloneTags($tags),
            );
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

        $state['createPrototype'] = $this->attachRelations(
            $this->makeBareTask(900_001, $fixed),
            tags: $this->cloneTags($tags),
        );
        $state['updatePrototype'] = $this->attachRelations(
            $this->makeBareTask(1, Carbon::parse('2024-01-15T13:00:00+00:00')),
            tags: $this->cloneTags($tags),
        );
        $state['updatePrototype']->title = 'NoORM updated title';
        $state['updatePrototype']->description = 'Updated';
        $state['updatePrototype']->status = Task::STATUS_DONE;
        $state['updatePrototype']->priority = Task::PRIORITY_HIGH;

        $state['warmed'] = true;
        self::saveWorkerCache($state);
    }

    public function isWarmed(): bool
    {
        return self::workerState()['warmed'];
    }

    /**
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
        $task->title = (string) ($data['title'] ?? $task->title);
        if (array_key_exists('description', $data)) {
            $task->description = $data['description'];
        }
        if (isset($data['status'])) {
            $task->status = (string) $data['status'];
        }
        if (isset($data['priority'])) {
            $task->priority = (string) $data['priority'];
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
        foreach (['title', 'description', 'status', 'priority'] as $field) {
            if (array_key_exists($field, $data)) {
                $task->{$field} = $data[$field];
            }
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

    private function makeBareTask(int $id, Carbon $fixed): Task
    {
        $task = new Task([
            'project_id' => 1,
            'title' => "NoORM Task {$id}",
            'description' => "Task {$id}",
            'status' => Task::STATUS_TODO,
            'priority' => Task::PRIORITY_MEDIUM,
            'due_date' => null,
        ]);
        $task->id = $id;
        $task->exists = true;
        $task->created_at = $fixed;
        $task->updated_at = $fixed;
        $task->syncOriginal();

        return $task;
    }

    private function makeProject(Carbon $fixed): Project
    {
        $project = new Project([
            'user_id' => 1,
            'name' => 'NoORM Project',
            'description' => null,
            'status' => Project::STATUS_ACTIVE,
        ]);
        $project->id = 1;
        $project->exists = true;
        $project->created_at = $fixed;
        $project->updated_at = $fixed;
        $project->syncOriginal();

        return $project;
    }

    /**
     * @return list<Tag>
     */
    private function makeTags(Carbon $fixed): array
    {
        $tags = [];
        for ($i = 1; $i <= self::TAGS_PER_TASK; ++$i) {
            $tag = new Tag([
                'name' => "noorm-tag-{$i}",
                'color' => sprintf('#%06X', 0x100000 + $i * 0x111111),
            ]);
            $tag->id = $i;
            $tag->exists = true;
            $tag->created_at = $fixed;
            $tag->updated_at = $fixed;
            $tag->syncOriginal();
            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * @param list<Tag> $tags
     *
     * @return Collection<int, Tag>
     */
    private function cloneTags(array $tags): Collection
    {
        return collect($tags)->map(fn (Tag $t) => clone $t);
    }

    private function makeComments(int $taskId, Carbon $fixed): Collection
    {
        $comments = new Collection;
        for ($c = 1; $c <= self::COMMENTS_PER_TASK; ++$c) {
            $comment = new Comment([
                'task_id' => $taskId,
                'content' => "NoORM comment {$c} on task {$taskId}",
            ]);
            $comment->id = ($taskId - 1) * self::COMMENTS_PER_TASK + $c;
            $comment->exists = true;
            $comment->created_at = $fixed;
            $comment->updated_at = $fixed;
            $comment->syncOriginal();
            $comments->push($comment);
        }

        return $comments;
    }

    private function attachRelations(
        Task $task,
        ?Project $project = null,
        ?Collection $comments = null,
        ?Collection $tags = null,
    ): Task {
        if ($project !== null) {
            $task->setRelation('project', $project);
        }
        if ($comments !== null) {
            $task->setRelation('comments', $comments);
        }
        if ($tags !== null) {
            $task->setRelation('tags', $tags);
        }

        return $task;
    }
}

<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentService
{
    /**
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, Comment>
     */
    public function paginate(int $perPage, array $with, ?int $taskId): LengthAwarePaginator
    {
        return Comment::query()
            ->with($with)
            ->when($taskId !== null, fn ($q) => $q->where('task_id', $taskId))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $with
     */
    public function find(int $id, array $with): Comment
    {
        return Comment::query()
            ->with($with)
            ->findOrFail($id);
    }

    public function createForTask(Task $task, string $content): Comment
    {
        return $task->comments()->create([
            'content' => $content,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Comment $comment, array $data): Comment
    {
        $comment->fill($data);
        $comment->save();

        return $comment->refresh();
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}

<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TagService
{
    /**
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, Tag>
     */
    public function paginate(int $perPage, array $with): LengthAwarePaginator
    {
        return Tag::query()
            ->with($with)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  list<string>  $with
     */
    public function find(int $id, array $with): Tag
    {
        return Tag::query()
            ->with($with)
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Tag
    {
        return Tag::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tag $tag, array $data): Tag
    {
        $tag->fill($data);
        $tag->save();

        return $tag->refresh();
    }

    public function delete(Tag $tag): void
    {
        $tag->delete();
    }
}

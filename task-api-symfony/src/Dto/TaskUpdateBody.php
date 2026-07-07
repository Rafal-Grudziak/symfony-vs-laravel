<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Task;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskUpdateBody
{
    /**
     * @param list<int>|null $tagIds
     */
    public function __construct(
        #[SerializedName('project_id')]
        #[Assert\Type('int')]
        #[Assert\Positive]
        public ?int $projectId = null,
        #[Assert\Length(max: 255)]
        public ?string $title = null,
        public ?string $description = null,
        #[Assert\Choice(choices: [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_DONE, Task::STATUS_CANCELLED])]
        public ?string $status = null,
        #[Assert\Choice(choices: [Task::PRIORITY_LOW, Task::PRIORITY_MEDIUM, Task::PRIORITY_HIGH, Task::PRIORITY_URGENT])]
        public ?string $priority = null,
        #[SerializedName('due_date')]
        public ?string $dueDate = null,
        #[SerializedName('tag_ids')]
        #[Assert\Type('array')]
        #[Assert\All([new Assert\Type('int'), new Assert\Positive()])]
        public ?array $tagIds = null,
    ) {
    }
}

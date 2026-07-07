<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class BulkCommentsBody
{
    public function __construct(
        #[SerializedName('task_id')]
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Positive]
        public int $taskId,
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Range(min: 1, max: 10000)]
        public int $count,
    ) {
    }
}

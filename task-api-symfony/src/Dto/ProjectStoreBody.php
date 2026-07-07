<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Project;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class ProjectStoreBody
{
    public function __construct(
        #[SerializedName('user_id')]
        #[Assert\NotNull]
        #[Assert\Type('int')]
        #[Assert\Positive]
        public int $userId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        public ?string $description = null,
        #[Assert\Choice(choices: [Project::STATUS_DRAFT, Project::STATUS_ACTIVE, Project::STATUS_ARCHIVED])]
        public ?string $status = null,
    ) {
    }
}

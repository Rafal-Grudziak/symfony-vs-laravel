<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class TagStoreBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[Assert\Length(max: 32)]
        public ?string $color = null,
    ) {
    }
}

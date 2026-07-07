<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class TagUpdateBody
{
    public function __construct(
        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,
        #[Assert\Length(min: 1, max: 32)]
        public ?string $color = null,
    ) {
    }
}

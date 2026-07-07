<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CommentStoreBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 65535)]
        public string $content,
    ) {
    }
}

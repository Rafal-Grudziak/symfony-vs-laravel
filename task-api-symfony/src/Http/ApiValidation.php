<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiValidation
{
    public static function violationResponse(
        ConstraintViolationListInterface $violations,
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ): JsonResponse {
        return new JsonResponse(self::violationPayload($violations), $status);
    }

    /**
     * @return array{message: string, errors: array<string, list<string>>}
     */
    public static function violationPayload(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $path = self::normalizePropertyPath($violation->getPropertyPath());
            $errors[$path][] = (string) $violation->getMessage();
        }

        return [
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ];
    }

    private static function normalizePropertyPath(string $propertyPath): string
    {
        $path = trim($propertyPath, '[]');

        return $path !== '' ? $path : '_';
    }
}

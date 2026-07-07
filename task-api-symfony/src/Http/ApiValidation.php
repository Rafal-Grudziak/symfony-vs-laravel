<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiValidation
{
    public static function violationResponse(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $path = trim($violation->getPropertyPath(), '[]');
            $errors[$path][] = (string) $violation->getMessage();
        }

        return new JsonResponse([
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

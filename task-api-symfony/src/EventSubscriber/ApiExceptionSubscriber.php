<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiValidation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();

        $validationFailed = self::findValidationFailedException($e);
        if ($validationFailed instanceof ValidationFailedException) {
            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            $event->setResponse(ApiValidation::violationResponse(
                $validationFailed->getViolations(),
                $status,
            ));
            $event->stopPropagation();

            return;
        }

        if (!$e instanceof HttpExceptionInterface) {
            return;
        }

        $status = $e->getStatusCode();
        $message = $e->getMessage();
        if ($message === '') {
            $message = $status === 404 ? 'Not Found' : 'Error';
        }

        $event->setResponse(new JsonResponse(['message' => $message], $status));
        $event->stopPropagation();
    }

    private static function findValidationFailedException(\Throwable $e): ?ValidationFailedException
    {
        $current = $e;
        while ($current !== null) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
            $current = $current->getPrevious();
        }

        return null;
    }
}

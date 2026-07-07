<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

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
}

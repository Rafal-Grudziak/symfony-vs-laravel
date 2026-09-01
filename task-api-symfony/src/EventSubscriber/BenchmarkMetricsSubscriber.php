<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Benchmark\BenchmarkMetricsCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class BenchmarkMetricsSubscriber implements EventSubscriberInterface
{
    public const HEADER_TRIGGER = 'X-Benchmark-Metrics';

    public const HEADER_QUERY_COUNT = 'X-Query-Count';

    public const HEADER_RESPONSE_TIME_MS = 'X-Response-Time-Ms';

    public const HEADER_REQUEST_ID = 'X-Benchmark-Request-Id';

    public function __construct(
        private readonly BenchmarkMetricsCollector $collector,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->collector->reset();

        if ($event->getRequest()->headers->get(self::HEADER_TRIGGER) === '1') {
            $this->collector->begin();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->collector->isEnabled()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(self::HEADER_QUERY_COUNT, (string) $this->collector->getSqlCount());
        $response->headers->set(self::HEADER_RESPONSE_TIME_MS, (string) round($this->collector->getElapsedMs(), 3));
        $response->headers->set(self::HEADER_REQUEST_ID, $this->collector->getRequestId());

        $this->collector->reset();
    }
}

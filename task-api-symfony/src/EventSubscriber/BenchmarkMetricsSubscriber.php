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

    public const HEADER_SQL_LOG = 'X-Benchmark-Sql-Log';

    public const HEADER_QUERY_COUNT = 'X-Query-Count';

    public const HEADER_RESPONSE_TIME_MS = 'X-Response-Time-Ms';

    public const HEADER_REQUEST_ID = 'X-Benchmark-Request-Id';

    public function __construct(
        private readonly BenchmarkMetricsCollector $collector,
        private readonly string $logsDir,
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

        $request = $event->getRequest();
        $metrics = $request->headers->get(self::HEADER_TRIGGER) === '1';
        $sqlLog = $request->headers->get(self::HEADER_SQL_LOG) === '1';

        if ($metrics || $sqlLog) {
            $this->collector->begin($sqlLog);
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

        $request = $event->getRequest();
        $response = $event->getResponse();
        $response->headers->set(self::HEADER_QUERY_COUNT, (string) $this->collector->getSqlCount());
        $response->headers->set(self::HEADER_RESPONSE_TIME_MS, (string) round($this->collector->getElapsedMs(), 3));
        $response->headers->set(self::HEADER_REQUEST_ID, $this->collector->getRequestId());

        if ($this->collector->isSqlDumpEnabled()) {
            $this->writeSqlDump($request->getMethod(), $request->getRequestUri());
        }

        $this->collector->reset();
    }

    private function writeSqlDump(string $method, string $uri): void
    {
        if (!is_dir($this->logsDir)) {
            mkdir($this->logsDir, 0775, true);
        }

        $payload = [
            'request_id' => $this->collector->getRequestId(),
            'framework' => 'symfony',
            'method' => $method,
            'uri' => $uri,
            'query_count' => $this->collector->getSqlCount(),
            'queries' => $this->collector->getQueries(),
            'logged_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        file_put_contents(
            $this->logsDir.'/sql_benchmark.log',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            FILE_APPEND | LOCK_EX,
        );
    }
}

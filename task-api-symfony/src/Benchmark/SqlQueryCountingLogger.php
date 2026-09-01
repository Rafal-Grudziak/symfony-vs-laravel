<?php

declare(strict_types=1);

namespace App\Benchmark;

use Psr\Log\AbstractLogger;

final class SqlQueryCountingLogger extends AbstractLogger
{
    public function __construct(
        private readonly BenchmarkMetricsCollector $collector,
    ) {
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if (!$this->collector->isEnabled()) {
            return;
        }

        if ($level !== 'debug' || !is_string($message)) {
            return;
        }

        if (!str_starts_with($message, 'Executing')) {
            return;
        }

        $this->collector->incrementSqlCount();
    }
}

<?php

declare(strict_types=1);

namespace App\Benchmark;

use Psr\Log\AbstractLogger;

/**
 * Zlicza wykonane zapytania SQL, gdy włączone jest zbieranie
 * metryk wykorzystywanych podczas testów wydajności.
 */
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

        if (str_starts_with($message, 'Executing')) {
            $this->collector->incrementSqlCount();
        }
    }
}

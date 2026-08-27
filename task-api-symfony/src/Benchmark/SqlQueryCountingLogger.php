<?php

declare(strict_types=1);

namespace App\Benchmark;

use Psr\Log\AbstractLogger;

/**
 * Zlicza wykonane zapytania SQL, gdy włączone jest zbieranie
 * metryk wykorzystywanych podczas testów wydajności.
 * Przy włączonym dumpie zapisuje treść SQL + bindingi z kontekstu DBAL.
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

        if (!str_starts_with($message, 'Executing')) {
            return;
        }

        $this->collector->incrementSqlCount();

        if ($this->collector->isSqlDumpEnabled()) {
            $sql = isset($context['sql']) && is_string($context['sql'])
                ? $context['sql']
                : $message;
            $this->collector->recordQuery($sql, $context['params'] ?? null);
        }
    }
}

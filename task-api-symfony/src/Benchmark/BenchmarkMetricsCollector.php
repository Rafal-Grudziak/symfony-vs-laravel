<?php

declare(strict_types=1);

namespace App\Benchmark;

/**
 * Request-scoped SQL count + wall time for {@see X_BENCHMARK_METRICS_HEADER}.
 */
final class BenchmarkMetricsCollector
{
    private bool $enabled = false;

    private float $startedAt = 0.0;

    private int $sqlCount = 0;

    public function reset(): void
    {
        $this->enabled = false;
        $this->startedAt = 0.0;
        $this->sqlCount = 0;
    }

    public function begin(): void
    {
        $this->enabled = true;
        $this->startedAt = microtime(true);
        $this->sqlCount = 0;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function incrementSqlCount(): void
    {
        if ($this->enabled) {
            ++$this->sqlCount;
        }
    }

    public function getSqlCount(): int
    {
        return $this->sqlCount;
    }

    public function getElapsedMs(): float
    {
        if (!$this->enabled) {
            return 0.0;
        }

        return (microtime(true) - $this->startedAt) * 1000;
    }
}

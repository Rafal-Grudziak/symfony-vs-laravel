<?php

declare(strict_types=1);

namespace App\Benchmark;

final class BenchmarkMetricsCollector
{
    private bool $enabled = false;

    private float $startedAt = 0.0;

    private int $sqlCount = 0;

    private string $requestId = '';

    public function reset(): void
    {
        $this->enabled = false;
        $this->startedAt = 0.0;
        $this->sqlCount = 0;
        $this->requestId = '';
    }

    public function begin(): void
    {
        $this->enabled = true;
        $this->startedAt = microtime(true);
        $this->sqlCount = 0;
        $this->requestId = bin2hex(random_bytes(8));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
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

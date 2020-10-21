<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
final class RateLimit
{
    private ?int $limit;
    private ?int $period;

    /**
     * @param array<string, int> $args
     */
    public function __construct(array $args = [])
    {
        $this->limit = $args['limit'] ?? null;
        $this->period = $args['period'] ?? null;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): RateLimit
    {
        $this->limit = $limit;

        return $this;
    }

    public function getPeriod(): ?int
    {
        return $this->period;
    }

    public function setPeriod(int $period): RateLimit
    {
        $this->period = $period;

        return $this;
    }
}

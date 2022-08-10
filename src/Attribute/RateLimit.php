<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class RateLimit
{
    private ?int $limit;
    private ?int $period;

    public function __construct(?int $limit = null, ?int $period = null)
    {
        $this->limit = $limit;
        $this->period = $period;
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

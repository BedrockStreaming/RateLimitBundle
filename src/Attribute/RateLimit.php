<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class RateLimit
{
    public function __construct(private ?int $limit = null, private ?int $period = null)
    {
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

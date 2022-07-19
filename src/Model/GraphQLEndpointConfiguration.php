<?php

namespace Bedrock\Bundle\RateLimitBundle\Model;

class GraphQLEndpointConfiguration
{
    public function __construct(private readonly ?int $limit, private readonly ?int $period, private readonly string $endpoint)
    {
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPeriod(): ?int
    {
        return $this->period;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}

<?php

namespace Bedrock\Bundle\RateLimitBundle\Model;

class GraphQLEndpointConfiguration
{
    public function __construct(private ?int $limit, private ?int $period, private string $endpoint)
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

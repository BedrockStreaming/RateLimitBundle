<?php

namespace Bedrock\Bundle\RateLimitBundle\Model;

class GraphQLEndpointConfiguration
{
    private ?int $limit;

    private ?int $period;

    private string $endpoint;

    public function __construct(?int $limit, ?int $period, string $endpoint)
    {
        $this->limit = $limit;
        $this->period = $period;
        $this->endpoint = $endpoint;
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

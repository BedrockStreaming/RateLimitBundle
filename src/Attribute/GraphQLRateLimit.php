<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Attribute;

use Bedrock\Bundle\RateLimitBundle\Model\GraphQLEndpointConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class GraphQLRateLimit
{
    /** @var array<GraphQLEndpointConfiguration> */
    private array $endpointConfigurations;

    /**
     * @param array<array<string, string|int|null>> $endpoints
     */
    public function __construct(array $endpoints = [])
    {
        $optionResolver = (new OptionsResolver())->setDefault('endpoints', function (OptionsResolver $endpointResolver) {
            $endpointResolver->setPrototype(true)
            ->setDefaults([
                'limit' => null,
                'period' => null,
            ])
            ->setRequired('endpoint')
            ->setAllowedTypes('endpoint', 'string')
            ->setAllowedTypes('limit', ['int', 'null'])
            ->setAllowedTypes('period', ['int', 'null']);
        });

        $resolvedArgs = $optionResolver->resolve(['endpoints' => $endpoints]);

        foreach ($resolvedArgs['endpoints'] as $endpoint) {
            $this->endpointConfigurations[] = new GraphQLEndpointConfiguration($endpoint['limit'], $endpoint['period'], $endpoint['endpoint']);
        }
    }

    /**
     * @return array<GraphQLEndpointConfiguration>
     */
    public function getEndpointConfigurations(): array
    {
        return $this->endpointConfigurations;
    }
}

<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Annotation;

use Bedrock\Bundle\RateLimitBundle\Model\GraphQLEndpointConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
final class GraphQLRateLimit
{
    /** @var array<GraphQLEndpointConfiguration> */
    private array $endpointConfigurations;

    /**
     * @param array<string, mixed> $args
     */
    public function __construct(array $args = [])
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

        $resolvedArgs = $optionResolver->resolve($args);

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

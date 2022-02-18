<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BedrockRateLimitExtension extends Extension
{
    /**
     * Override conf key for this bundle
     */
    public function getAlias(): string
    {
        return 'bedrock_rate_limit';
    }

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('bedrock_rate_limit.limit', $config['limit']);
        $container->setParameter('bedrock_rate_limit.period', $config['period']);
        $container->setParameter('bedrock_rate_limit.limit_by_route', $config['limit_by_route']);
        $container->setParameter('bedrock_rate_limit.display_headers', $config['display_headers']);
        $container->setParameter('bedrock_rate_limit.routes', $config['routes']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yml');
    }
}

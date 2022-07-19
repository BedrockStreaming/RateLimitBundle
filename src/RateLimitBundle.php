<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle;

use Bedrock\Bundle\RateLimitBundle\DependencyInjection\BedrockRateLimitExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class RateLimitBundle extends Bundle
{
    public function getContainerExtension(): BedrockRateLimitExtension
    {
        return new BedrockRateLimitExtension();
    }
}

<?php

namespace Bedrock\Bundle\RateLimitBundle\RateLimitModifier;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Symfony\Component\HttpFoundation\Request;

class RequestAttributeRateLimitModifier implements RateLimitModifierInterface
{
    private string $attributeName;

    public function __construct(string $attributeName)
    {
        $this->attributeName = $attributeName;
    }

    public function support(Request $request): bool
    {
        return $request->attributes->has($this->attributeName);
    }

    public function modifyRateLimit(Request $request, RateLimit $rateLimit): void
    {
        /** @var string */
        $route = $request->attributes->get($this->attributeName);
        $rateLimit->varyHashOn($this->attributeName, $route);
    }
}

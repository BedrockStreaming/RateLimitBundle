<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\RateLimitModifier;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Symfony\Component\HttpFoundation\Request;

class HttpMethodRateLimitModifier implements RateLimitModifierInterface
{
    public function support(Request $request): bool
    {
        // will be called for all requests with @RateLimit activated
        // i.e.: rate limit will at least vary on http method
        return true;
    }

    public function modifyRateLimit(Request $request, RateLimit $rateLimit): void
    {
        $rateLimit->varyHashOn('http_method', $request->getMethod());
    }
}

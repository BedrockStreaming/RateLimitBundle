<?php

namespace Bedrock\Bundle\RateLimitBundle\RateLimitModifier;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Symfony\Component\HttpFoundation\Request;

interface RateLimitModifierInterface
{
    public function support(Request $request): bool;

    public function modifyRateLimit(Request $request, RateLimit $rateLimit): void;
}

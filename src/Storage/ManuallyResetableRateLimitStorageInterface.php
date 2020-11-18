<?php

namespace Bedrock\Bundle\RateLimitBundle\Storage;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;

interface ManuallyResetableRateLimitStorageInterface extends RateLimitStorageInterface
{
    /**
     * Deletes stored rate limit
     */
    public function resetRateLimit(RateLimit $rateLimit): void;
}

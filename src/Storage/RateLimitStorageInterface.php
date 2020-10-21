<?php

namespace Bedrock\Bundle\RateLimitBundle\Storage;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;

interface RateLimitStorageInterface
{
    /**
     * Retrieves the stored rate limit or returns null
     */
    public function getStoredRateLimit(RateLimit $rateLimit): ?StoredRateLimit;

    /**
     * Stores rate limit with hits = 1
     */
    public function storeRateLimit(RateLimit $rateLimit): StoredRateLimit;

    /**
     * Increases +1 hits number
     */
    public function incrementHits(StoredRateLimit $storedRateLimit): StoredRateLimit;
}

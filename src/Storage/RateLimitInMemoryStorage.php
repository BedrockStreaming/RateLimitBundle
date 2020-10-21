<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Storage;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;

class RateLimitInMemoryStorage implements NoTTLRateLimitStorageInterface
{
    /** @var array<string, StoredRateLimit> */
    private static $storedRateLimits = [];

    public function getStoredRateLimit(RateLimit $rateLimit): ?StoredRateLimit
    {
        return self::$storedRateLimits[$rateLimit->getHash()] ?? null;
    }

    public function storeRateLimit(RateLimit $rateLimit): StoredRateLimit
    {
        $validUntil = \DateTimeImmutable::createFromFormat('U', (string) (time() + $rateLimit->getPeriod()));
        if ($validUntil === false) {
            throw new \RuntimeException('Invalid rate limit period');
        }

        self::$storedRateLimits[$rateLimit->getHash()] = new StoredRateLimit($rateLimit, 1, $validUntil);

        return self::$storedRateLimits[$rateLimit->getHash()];
    }

    public function incrementHits(StoredRateLimit $storedRateLimit): StoredRateLimit
    {
        self::$storedRateLimits[$storedRateLimit->getHash()] = $storedRateLimit->withHits($storedRateLimit->getHits() + 1);

        return self::$storedRateLimits[$storedRateLimit->getHash()];
    }

    public function resetRateLimit(RateLimit $rateLimit): void
    {
        if (array_key_exists($rateLimit->getHash(), self::$storedRateLimits)) {
            unset(self::$storedRateLimits[$rateLimit->getHash()]);
        }
    }

    public static function reset(): void
    {
        self::$storedRateLimits = [];
    }
}

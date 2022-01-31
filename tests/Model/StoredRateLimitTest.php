<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\Model;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;
use PHPUnit\Framework\TestCase;

class StoredRateLimitTest extends TestCase
{
    public function testItCanTellIfItIsOutdated(): void
    {
        $storedRateLimit = new StoredRateLimit(new RateLimit(10, 10), 1, new \DateTimeImmutable('+1 day'));
        $this->assertFalse($storedRateLimit->isOutdated());

        $storedRateLimit = new StoredRateLimit(new RateLimit(10, 10), 1, new \DateTimeImmutable('-1 day'));
        $this->assertTrue($storedRateLimit->isOutdated());
    }

    public function testWithHitsCreateANewObject(): void
    {
        $storedRateLimit = new StoredRateLimit(new RateLimit(10, 10), 1, new \DateTimeImmutable('+1 day'));
        $newStoredRateLimit = $storedRateLimit->withHits(5);

        $this->assertNotSame($storedRateLimit, $newStoredRateLimit);
        $this->assertSame(5, $newStoredRateLimit->getHits());
    }

    public function testItComputesLimitReachedMessage(): void
    {
        $storedRateLimit = new StoredRateLimit($rateLimit = new RateLimit(1000, 60), 1, new \DateTimeImmutable('2020-06-01'));
        $rateLimit->varyHashOn('http_method', 'GET');
        $rateLimit->varyHashOn('customer', 'customer-test');

        $this->assertSame(
            [
                'message' => 'Too many requests. Only 1000 calls allowed every 60 seconds.',
                'limit' => 1000,
                'period' => 60,
                'until' => '2020-06-01T00:00:00+00:00',
                'vary' => '{"http_method":"GET","customer":"customer-test"}',
            ],
            $storedRateLimit->getLimitReachedOutput()
        );
    }
}

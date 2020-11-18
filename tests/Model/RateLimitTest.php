<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\Model;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    public function testCreateARateLimitWithNegativeLimitThrowAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit and period must be > 0');

        new RateLimit(-10, 100);
    }

    public function testCreateARateLimitWithNegativePeriodThrowAnException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit and period must be > 0');

        new RateLimit(10, -100);
    }

    public function testItThrowsExceptionWhenComputingDiscriminatorAndNoVaryProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot compute rate limit discriminator with an empty vary.');

        $rateLimit = new RateLimit(1000, 60);
        $rateLimit->getDiscriminator();
    }

    public function testItComputesDiscriminator(): void
    {
        $rateLimit = new RateLimit(1000, 60);
        $rateLimit->varyHashOn('method', 'GET');
        $this->assertSame('{"method":"GET"}', $rateLimit->getDiscriminator());

        $rateLimit->varyHashOn('url', 'http://url');
        $this->assertSame('{"method":"GET","url":"http:\/\/url"}', $rateLimit->getDiscriminator());
    }

    public function testItThrowsExceptionWhenComputingHashKeyAndNoVaryProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot compute rate limit discriminator with an empty vary.');

        $rateLimit = new RateLimit(1000, 60);
        $rateLimit->getHash();
    }

    public function testItComputesHash(): void
    {
        $rateLimit = new RateLimit(1000, 60);
        $rateLimit->varyHashOn('method', 'GET');
        $this->assertSame('aa33ceaf98a63b8bd52b3986a9ee06cb', $rateLimit->getHash());

        $rateLimit->varyHashOn('url', 'http://url');
        $this->assertSame('8288acbaf1221b58a2440c01448f5021', $rateLimit->getHash());
    }
}

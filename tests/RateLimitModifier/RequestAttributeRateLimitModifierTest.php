<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\RateLimitModifier;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RequestAttributeRateLimitModifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestAttributeRateLimitModifierTest extends TestCase
{
    public function testItSupportsRequestIfUidExists(): void
    {
        $rateLimitModifier = new RequestAttributeRateLimitModifier('uid');
        $this->assertFalse($rateLimitModifier->support(new Request()));
        $this->assertTrue($rateLimitModifier->support(new Request([], [], ['uid' => 'uid-test'])));
    }

    public function testItAddVaryOnUid(): void
    {
        $rateLimitModifier = new RequestAttributeRateLimitModifier('uid');
        $request = new Request([], [], ['uid' => 'uid-test']);
        $rateLimitModifier->modifyRateLimit($request, $rateLimit = new RateLimit(10, 10));
        $this->assertSame('{"uid":"uid-test"}', $rateLimit->getDiscriminator());
    }
}

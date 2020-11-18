<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\RateLimitModifier;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\HttpMethodRateLimitModifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class HttpMethodRateLimitModifierTest extends TestCase
{
    public function testItAlwaysSupportsRequest(): void
    {
        $rateLimitModifier = new HttpMethodRateLimitModifier();
        $this->assertTrue($rateLimitModifier->support(new Request()));
    }

    /**
     * @dataProvider itAddVaryOnHttpMethodProvider
     */
    public function testItAddVaryOnHttpMethod(string $method): void
    {
        $rateLimitModifier = new HttpMethodRateLimitModifier();

        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => $method]);
        $rateLimitModifier->modifyRateLimit($request, $rateLimit = new RateLimit(10, 10));

        $this->assertSame("{\"http_method\":\"$method\"}", $rateLimit->getDiscriminator());
    }

    /**
     * @return array<array<string, string>>
     */
    public function itAddVaryOnHttpMethodProvider(): array
    {
        return [
            [
                'method' => 'GET',
            ],
            [
                'method' => 'POST',
            ],
            [
                'method' => 'PATCH',
            ],
        ];
    }
}

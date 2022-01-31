<?php

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\EventListener\AddRateLimitHeadersListener;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AddRateLimitHeadersListenerTest extends TestCase
{
    public function testItDoesNotSetHeadersIfNoRateLimitProvidedInRequest(): void
    {
        $addRateLimitHeadersListener = new AddRateLimitHeadersListener();
        $addRateLimitHeadersListener->onKernelResponse($event = $this->createEvent());

        $response = $event->getResponse();
        $this->assertFalse($response->headers->has('x-rate-limit'));
        $this->assertFalse($response->headers->has('x-rate-limit-hits'));
        $this->assertFalse($response->headers->has('x-rate-limit-until'));
        $this->assertFalse($response->headers->has('retry-after'));
    }

    public function testItSetsHeadersIfRateLimitProvidedInRequest(): void
    {
        $addRateLimitHeadersListener = new AddRateLimitHeadersListener();
        $addRateLimitHeadersListener->onKernelResponse(
            $event = $this->createEvent(
                new Request(
                    [],
                    [],
                    [
                        '_stored_rate_limit' => new StoredRateLimit(
                            new RateLimit(1000, 60), 4, $validUntil = new \DateTimeImmutable()
                        ),
                    ]
                )
            )
        );

        $response = $event->getResponse();
        $this->assertSame(1000, (int) $response->headers->get('x-rate-limit'));
        $this->assertSame(4, (int) $response->headers->get('x-rate-limit-hits'));
        $this->assertSame($validUntil->format('c'), $response->headers->get('x-rate-limit-until'));
        $this->assertSame('0', $response->headers->get('retry-after'));
    }

    private function createEvent(Request $request = null): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request ?? new Request(),
            HttpKernelInterface::MASTER_REQUEST,
            new Response()
        );
    }
}

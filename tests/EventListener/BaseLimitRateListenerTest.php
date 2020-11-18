<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

abstract class BaseLimitRateListenerTest extends TestCase
{
    protected function createEventWithRateLimitInRequest(): ControllerArgumentsEvent
    {
        $rateLimit = new RateLimit(1000, 60);
        $rateLimit->varyHashOn('http_method', 'GET');

        return $this->createEvent(
            new Request(
                [],
                [],
                ['_rate_limit' => $rateLimit]
            )
        );
    }

    protected function createEvent(Request $request = null): ControllerArgumentsEvent
    {
        $controller = new class() {
            /**
             * @RateLimit()
             */
            public function action(): void
            {
            }
        };

        return new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [$controller, 'action'],
            [],
            $request ?? new Request(),
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    /**
     * @return StoredRateLimit|MockObject
     *
     * Mocking this value object is needed otherwise RateLimit\FunctionalTest will fail
     * because of a bug from outer space in ClockMock
     */
    protected function mockStoredRateLimit(RateLimit $rateLimit, int $hits, \DateTimeImmutable $validUntil)
    {
        $mock = $this->createMock(StoredRateLimit::class);
        $mock->expects($this->any())->method('getHash')->willReturn($rateLimit->getHash());
        $mock->expects($this->any())->method('getLimit')->willReturn($rateLimit->getLimit());
        $mock->expects($this->any())->method('getHits')->willReturn($hits);
        $mock->expects($this->any())->method('getValidUntil')->willReturn($validUntil);
        $mock->expects($this->any())->method('isOutdated')->willReturn($validUntil < new \DateTimeImmutable());

        return $mock;
    }
}

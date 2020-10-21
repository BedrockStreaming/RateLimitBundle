<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\EventListener\LimitRateListener;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;
use Bedrock\Bundle\RateLimitBundle\Storage\RateLimitStorageInterface;
use Symfony\Component\HttpFoundation\Response;

class LimitRateListenerTest extends BaseLimitRateListenerTest
{
    private LimitRateListener $limitRateListener;
    /** @var RateLimitStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $storage;

    public function setUp(): void
    {
        $this->limitRateListener = new LimitRateListener(
            $this->storage = $this->createMock(RateLimitStorageInterface::class)
        );
    }

    public function testItDoesNotCheckRateLimitIfItIsNotInRequest(): void
    {
        $event = $this->createEvent();

        $this->storage->expects($this->never())->method('getStoredRateLimit');

        $this->limitRateListener->onKernelController($event);

        $this->assertFalse($event->getRequest()->attributes->has('_stored_rate_limit'));
    }

    public function testItStoresRateLimitIfNoneIsStored(): void
    {
        $event = $this->createEventWithRateLimitInRequest();
        $rateLimit = $event->getRequest()->attributes->get('_rate_limit');

        $this->storage->expects($this->once())
            ->method('getStoredRateLimit')
            ->with($rateLimit)
            ->willReturn(null);

        $this->storage->expects($this->once())->method('storeRateLimit')->with($rateLimit);

        $this->storage->expects($this->never())->method('incrementHits');

        $oldController = $event->getController();
        $this->limitRateListener->onKernelController($event);
        $this->assertSame($oldController, $event->getController());
        $this->assertInstanceOf(StoredRateLimit::class, $event->getRequest()->attributes->get('_stored_rate_limit'));
    }

    public function testItResetsAndStoresNewRateLimitIfCurrentOneIsOutdated(): void
    {
        $event = $this->createEventWithRateLimitInRequest();
        $rateLimit = $event->getRequest()->attributes->get('_rate_limit');

        $this->storage->expects($this->once())
            ->method('getStoredRateLimit')
            ->with($rateLimit)
            ->willReturn(
                $storedRateLimit = $this->mockStoredRateLimit($rateLimit, 1, new \DateTimeImmutable('1 day ago'))
            );

        $this->storage->expects($this->once())->method('storeRateLimit')->with($rateLimit);

        $this->storage->expects($this->never())->method('incrementHits');

        $oldController = $event->getController();
        $this->limitRateListener->onKernelController($event);
        $this->assertSame($oldController, $event->getController());
        $this->assertInstanceOf(StoredRateLimit::class, $event->getRequest()->attributes->get('_stored_rate_limit'));
    }

    public function testItDecreasesLimitIfRateLimitIsValid(): void
    {
        $event = $this->createEventWithRateLimitInRequest();
        $rateLimit = $event->getRequest()->attributes->get('_rate_limit');

        $this->storage->expects($this->once())
            ->method('getStoredRateLimit')
            ->with($rateLimit)
            ->willReturn(
                $storedRateLimit = $this->mockStoredRateLimit($rateLimit, 4, new \DateTimeImmutable('+1 day'))
            );

        $this->storage->expects($this->once())->method('incrementHits')->with($storedRateLimit);

        $this->storage->expects($this->never())->method('storeRateLimit');

        $oldController = $event->getController();
        $this->limitRateListener->onKernelController($event);
        $this->assertSame($oldController, $event->getController());

        $this->assertInstanceOf(StoredRateLimit::class, $event->getRequest()->attributes->get('_stored_rate_limit'));
    }

    public function testItSetsABlockingResponseIfLimitIsReached(): void
    {
        $event = $this->createEventWithRateLimitInRequest();
        $rateLimit = $event->getRequest()->attributes->get('_rate_limit');

        $this->storage->expects($this->once())->method('getStoredRateLimit')->willReturn(
            $this->mockStoredRateLimit($rateLimit, 1000, new \DateTimeImmutable('+1 day'))
        );
        $this->storage->expects($this->once())->method('incrementHits');

        $this->storage->expects($this->never())->method('storeRateLimit');

        $oldController = $event->getController();
        $this->limitRateListener->onKernelController($event);

        $newController = $event->getController();
        $this->assertNotSame($oldController, $newController);
        /** @var Response $response */
        $response = $newController();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());

        $this->assertInstanceOf(StoredRateLimit::class, $event->getRequest()->attributes->get('_stored_rate_limit'));
    }

    public function testItSetsABlockingResponseIfLimitIsExceeded(): void
    {
        $event = $this->createEventWithRateLimitInRequest();
        $rateLimit = $event->getRequest()->attributes->get('_rate_limit');

        $this->storage->expects($this->once())->method('getStoredRateLimit')->willReturn(
            $this->mockStoredRateLimit($rateLimit, 1001, new \DateTimeImmutable('+1 day'))
        );

        $this->limitRateListener->onKernelController($event);
        /** @var Response $response */
        $response = ($event->getController())();
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('content-type'));
    }
}

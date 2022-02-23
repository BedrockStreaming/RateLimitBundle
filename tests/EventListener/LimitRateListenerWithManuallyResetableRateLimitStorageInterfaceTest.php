<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\EventListener\LimitRateListener;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;
use Bedrock\Bundle\RateLimitBundle\Storage\ManuallyResetableRateLimitStorageInterface;

class LimitRateListenerWithManuallyResetableRateLimitStorageInterfaceTest extends BaseLimitRateListenerTest
{
    private LimitRateListener $limitRateListener;
    /** @var ManuallyResetableRateLimitStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $storage;

    public function setUp(): void
    {
        $this->limitRateListener = new LimitRateListener(
            $this->storage = $this->createMock(ManuallyResetableRateLimitStorageInterface::class),
            false
        );
    }

    public function testItResetsAndStoresNewRateLimitIfCurrentOneIsOutdated(): void
    {
        $event = $this->createEventWithRateLimitInRequest();
        /** @var RateLimit $rateLimit */
        $rateLimit = $event->getRequest()->attributes->get('_rate_limit');

        $this->storage->expects($this->once())
            ->method('getStoredRateLimit')
            ->with($rateLimit)
            ->willReturn(
                $storedRateLimit = $this->mockStoredRateLimit($rateLimit, 1, new \DateTimeImmutable('1 day ago'))
            );

        $this->storage->expects($this->once())->method('resetRateLimit')->with($rateLimit);
        $this->storage->expects($this->once())->method('storeRateLimit')->with($rateLimit);

        $this->storage->expects($this->never())->method('incrementHits');

        $oldController = $event->getController();
        $this->limitRateListener->onKernelController($event);
        $this->assertSame($oldController, $event->getController());
        $this->assertInstanceOf(StoredRateLimit::class, $event->getRequest()->attributes->get('_stored_rate_limit'));
    }
}

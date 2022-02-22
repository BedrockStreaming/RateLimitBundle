<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\EventListener\ReadRateLimitConfigurationListener;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ReadRateLimitConfigurationListenerTest extends TestCase
{
    private ReadRateLimitConfigurationListener $readRateLimitConfigurationListener;

    /** @var array<RateLimitModifierInterface|MockObject> */
    private $rateLimitModifiers;

    private int $limitDefaultValue = 1000;

    private int $periodDefaultValue = 60;

    /**
     * @param array<array<string, int>> $routes
     */
    public function createReadRateLimitConfigurationListener(array $routes): void
    {
        $this->readRateLimitConfigurationListener = new ReadRateLimitConfigurationListener(
            $this->rateLimitModifiers = [
                $this->createMock(RateLimitModifierInterface::class),
                $this->createMock(RateLimitModifierInterface::class),
            ],
            $this->limitDefaultValue,
            $this->periodDefaultValue,
            $routes
        );
    }

    public function testItDoesNotSetRateLimitIfNoConfigurationProvided(): void
    {
        $this->createReadRateLimitConfigurationListener([]);
        $event = $this->createEvent();

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitConfigurationListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    public function testItSetsRateLimitIfConfigurationProvidedWithDefaultValue(): void
    {
        $this->createReadRateLimitConfigurationListener(['some_route_name' => []]);
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $event = $this->createEvent($request);

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit($this->limitDefaultValue, $this->periodDefaultValue);
        $rateLimit->varyHashOn('_route', 'some_route_name');
        $this->rateLimitModifiers[0]->expects($this->once())->method('modifyRateLimit')->with($request, $rateLimit);

        $this->rateLimitModifiers[1]->expects($this->once())->method('support')->willReturn(false);
        $this->rateLimitModifiers[1]->expects($this->never())->method('modifyRateLimit');

        $this->readRateLimitConfigurationListener->onKernelController($event);
        $this->assertTrue($event->getRequest()->attributes->has('_rate_limit'));

        $this->assertEquals(
            $rateLimit,
            $event->getRequest()->attributes->get('_rate_limit')
        );
    }

    public function testItSetsRateLimitIfConfigurationProvidedWithCustomValue(): void
    {
        $this->createReadRateLimitConfigurationListener([
            'some_route_name' => [
                'limit' => 10,
                'period' => 5,
            ],
        ]);
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $event = $this->createEvent($request);

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit(10, 5);
        $rateLimit->varyHashOn('_route', 'some_route_name');
        $this->rateLimitModifiers[0]->expects($this->once())->method('modifyRateLimit')->with($request, $rateLimit);

        $this->rateLimitModifiers[1]->expects($this->once())->method('support')->willReturn(false);
        $this->rateLimitModifiers[1]->expects($this->never())->method('modifyRateLimit');

        $this->readRateLimitConfigurationListener->onKernelController($event);
        $this->assertTrue($event->getRequest()->attributes->has('_rate_limit'));

        $this->assertEquals(
            $rateLimit,
            $event->getRequest()->attributes->get('_rate_limit')
        );
    }

    public function testItSetsRateLimitIfConfigurationProvidedWithCustomValueOnMoreThanOneRoute(): void
    {
        $this->createReadRateLimitConfigurationListener([
            'some_route_name' => [
                'limit' => 100,
            ],
            'an_other_route' => [
                'period' => 15,
            ],
        ]);
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $event = $this->createEvent($request);

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit(100, $this->periodDefaultValue);
        $rateLimit->varyHashOn('_route', 'some_route_name');
        $this->rateLimitModifiers[0]->expects($this->once())->method('modifyRateLimit')->with($request, $rateLimit);

        $this->rateLimitModifiers[1]->expects($this->once())->method('support')->willReturn(false);
        $this->rateLimitModifiers[1]->expects($this->never())->method('modifyRateLimit');

        $this->readRateLimitConfigurationListener->onKernelController($event);
        $this->assertTrue($event->getRequest()->attributes->has('_rate_limit'));

        $this->assertEquals(
            $rateLimit,
            $event->getRequest()->attributes->get('_rate_limit')
        );
    }

    protected function createEvent(Request $request = null, string $serviceId = null): ControllerEvent
    {
        $request = $request ?? new Request();
        $request->attributes->set('_controller', $serviceId ?? FakeInvokableClass::class);
        $request->attributes->set('_route', 'some_route_name');

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            new FakeInvokableClass(),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }
}

class FakeInvokableClass
{
    public function __invoke(): void
    {
    }
}

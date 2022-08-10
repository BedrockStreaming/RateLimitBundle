<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\Attribute\RateLimit as RateLimitAttribute;
use Bedrock\Bundle\RateLimitBundle\EventListener\ReadRateLimitAttributeListener;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ReadRateLimitAttributeListenerTest extends TestCase
{
    private ReadRateLimitAttributeListener $readRateLimitAttributeListener;

    /** @var array<RateLimitModifierInterface|MockObject> */
    private array $rateLimitModifiers;

    private int $limitDefaultValue = 1000;

    private int $periodDefaultValue = 60;

    /** @var MockObject|ContainerInterface */
    private $container;

    public function createReadRateLimitAttributeListener(bool $defaultLimitByRouteValue = false): void
    {
        $this->readRateLimitAttributeListener = new ReadRateLimitAttributeListener(
            $this->container = $this->createMock(ContainerInterface::class),
            $this->rateLimitModifiers = [
                $this->createMock(RateLimitModifierInterface::class),
                $this->createMock(RateLimitModifierInterface::class),
            ],
            $this->limitDefaultValue,
            $this->periodDefaultValue,
            $defaultLimitByRouteValue
        );
    }

    public function testItDoesNotSetRateLimitIfNoAttributeProvided(): void
    {
        $this->createReadRateLimitAttributeListener();
        $event = $this->createEvent(null, FakeInvokableClassWithoutRateLimit::class);

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitAttributeListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    /**
     * @dataProvider servicesAliasDataProvider
     */
    public function testItSetRateLimitIfNoAttributeProvidedAndServiceAliasIsUsed(string $serviceAlias): void
    {
        $this->createReadRateLimitAttributeListener();

        $this->container->expects($this->once())
            ->method('get')
            ->willReturn(new FakeInvokableClassWithoutRateLimit());

        $event = $this->createEvent(null, $serviceAlias);

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitAttributeListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    /**
     * @return \Generator<array<string>>
     */
    public function servicesAliasDataProvider(): \Generator
    {
        yield 'service alias with :' => [
            'fake.invokable.class:__invoke',
        ];
        yield 'service alias with ::' => [
            'fake.invokable.class::__invoke',
        ];
    }

    public function testItSetsRateLimitIfAttributeProvidedWithDefaultValue(): void
    {
        $this->createReadRateLimitAttributeListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $event = $this->createEvent($request);

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit($this->limitDefaultValue, $this->periodDefaultValue);
        $this->rateLimitModifiers[0]->expects($this->once())->method('modifyRateLimit')->with($request, $rateLimit);

        $this->rateLimitModifiers[1]->expects($this->once())->method('support')->willReturn(false);
        $this->rateLimitModifiers[1]->expects($this->never())->method('modifyRateLimit');

        $this->readRateLimitAttributeListener->onKernelController($event);
        $this->assertTrue($event->getRequest()->attributes->has('_rate_limit'));

        $this->assertEquals(
            $rateLimit,
            $event->getRequest()->attributes->get('_rate_limit')
        );
    }

    /**
     * @dataProvider rateLimitConfigurationDataProvider
     */
    public function testItSetsRateLimitIfAttributeProvidedWithCustomValue(bool $isLimitByRouteEnbaled): void
    {
        $this->createReadRateLimitAttributeListener($isLimitByRouteEnbaled);

        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag(['_route' => 'a-random-route']);
        $event = $this->createEventWithAttribute($request);

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        if ($isLimitByRouteEnbaled) {
            $rateLimit = new RateLimit(10, 5);
            $rateLimit->varyHashOn('_route', 'a-random-route');
        } else {
            $rateLimit = new RateLimit($this->limitDefaultValue, $this->periodDefaultValue);
        }
        $this->rateLimitModifiers[0]->expects($this->once())->method('modifyRateLimit')->with($request, $rateLimit);

        $this->rateLimitModifiers[1]->expects($this->once())->method('support')->willReturn(false);
        $this->rateLimitModifiers[1]->expects($this->never())->method('modifyRateLimit');

        $this->readRateLimitAttributeListener->onKernelController($event);
        $this->assertTrue($event->getRequest()->attributes->has('_rate_limit'));

        $this->assertEquals(
            $rateLimit,
            $event->getRequest()->attributes->get('_rate_limit')
        );
    }

    /**
     * @return \Generator<array<bool>>
     */
    public function rateLimitConfigurationDataProvider(): \Generator
    {
        yield 'rate_limit_by_route_is_disabled' => [
            false,
        ];

        yield 'rate_limit_by_route_is_enabled' => [
            true,
        ];
    }

    protected function createEvent(Request $request = null, string $serviceId = null): ControllerEvent
    {
        $request = $request ?? new Request();
        $request->attributes->set('_controller', $serviceId ?? FakeInvokableClassWithDefaultRateLimit::class);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            new FakeInvokableClassWithDefaultRateLimit(),
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    public function createEventWithAttribute(Request $request): ControllerEvent
    {
        $request->attributes->set('_controller', FakeClassWithRateLimit::class.'::action');

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [new FakeClassWithRateLimit(), 'action'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }
}

class FakeInvokableClassWithoutRateLimit
{
    public function __invoke(): void
    {
    }
}

class FakeInvokableClassWithDefaultRateLimit
{
    #[RateLimitAttribute]
    public function __invoke(): void
    {
    }
}

class FakeClassWithRateLimit
{
    #[RateLimitAttribute(limit: 10, period: 5)]
    public function action(): void
    {
    }
}

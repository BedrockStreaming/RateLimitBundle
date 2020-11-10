<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\Annotation\RateLimit as RateLimitAnnotation;
use Bedrock\Bundle\RateLimitBundle\EventListener\ReadRateLimitAnnotationListener;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ReadRateLimitAnnotationListenerTest extends TestCase
{
    private ReadRateLimitAnnotationListener $readRateLimitAnnotationListener;
    /** @var AnnotationReader|MockObject */
    private $annotationReader;
    /** @var array<RateLimitModifierInterface|MockObject> */
    private $rateLimitModifiers;
    private int $limitDefaultValue = 1000;
    private int $periodDefaultValue = 60;
    /** @var MockObject|ContainerInterface */
    private $container;

    public function createReadRateLimitAnnotationListerner(bool $defaultLimitByRouteValue = false): void
    {
        $this->readRateLimitAnnotationListener = new ReadRateLimitAnnotationListener(
            $this->container = $this->createMock(ContainerInterface::class),
            $this->annotationReader = $this->createMock(AnnotationReader::class),
            $this->rateLimitModifiers = [
                $this->createMock(RateLimitModifierInterface::class),
                $this->createMock(RateLimitModifierInterface::class),
            ],
            $this->limitDefaultValue,
            $this->periodDefaultValue,
            $defaultLimitByRouteValue
        );
    }

    public function testItDoesNotSetRateLimitIfNoAnnotationProvided(): void
    {
        $this->createReadRateLimitAnnotationListerner();
        $event = $this->createEvent();

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(null);

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitAnnotationListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    public function testItSetRateLimitIfNoAnnotationProvidedAndServiceAliasIsUsed(): void
    {
        $this->createReadRateLimitAnnotationListerner();

        $this->container->expects($this->once())
            ->method('get')
            ->willReturn(new FakeInvokableClassWithDefaultRateLimit());

        $event = $this->createEvent(null, true);

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(null);

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitAnnotationListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    public function testItSetsRateLimitIfAnnotationProvidedWithDefaultValue(): void
    {
        $this->createReadRateLimitAnnotationListerner();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $event = $this->createEvent($request);

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(new RateLimitAnnotation());

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit($this->limitDefaultValue, $this->periodDefaultValue);
        $this->rateLimitModifiers[0]->expects($this->once())->method('modifyRateLimit')->with($request, $rateLimit);

        $this->rateLimitModifiers[1]->expects($this->once())->method('support')->willReturn(false);
        $this->rateLimitModifiers[1]->expects($this->never())->method('modifyRateLimit');

        $this->readRateLimitAnnotationListener->onKernelController($event);
        $this->assertTrue($event->getRequest()->attributes->has('_rate_limit'));

        $this->assertEquals(
            $rateLimit,
            $event->getRequest()->attributes->get('_rate_limit')
        );
    }

    /**
     * @dataProvider rateLimitConfigurationDataProvider
     */
    public function testItSetsRateLimitIfAnnotationProvidedWithCustomValue(bool $isLimitByRouteEnbaled): void
    {
        $this->createReadRateLimitAnnotationListerner($isLimitByRouteEnbaled);

        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag(['_route' => 'a-random-route']);
        $event = $this->createEventWithAnnotation($request);

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(new RateLimitAnnotation(['limit' => 10, 'period' => 5]));

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

        $this->readRateLimitAnnotationListener->onKernelController($event);
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

    protected function createEvent(Request $request = null, bool $useAlias = false): ControllerEvent
    {
        $request = $request ?? new Request();
        $request->attributes->set('_controller', $useAlias ? 'fake.invokable.class:__invoke' : FakeInvokableClassWithDefaultRateLimit::class);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            new FakeInvokableClassWithDefaultRateLimit(),
            $request ?? new Request(),
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    public function createEventWithAnnotation(Request $request): ControllerEvent
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

class FakeInvokableClassWithDefaultRateLimit
{
    /**
     * @RateLimit()
     */
    public function __invoke(): void
    {
    }
}

class FakeClassWithRateLimit
{
    /**
     * @RateLimite(
     *     limit=10,
     *     period=5
     * )
     */
    public function action(): void
    {
    }
}

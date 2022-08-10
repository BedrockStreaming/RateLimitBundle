<?php

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\Attribute\GraphQLRateLimit as GraphQLRateLimitAttribute;
use Bedrock\Bundle\RateLimitBundle\EventListener\ReadGraphQLRateLimitAttributeListener;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ReadGraphQLRateLimitAttributeListenerTest extends TestCase
{
    private ReadGraphQLRateLimitAttributeListener $readRateLimitAttributeListener;

    /** @var array<RateLimitModifierInterface|MockObject> */
    private array $rateLimitModifiers;

    private int $limitDefaultValue = 1000;

    private int $periodDefaultValue = 60;

    /** @var MockObject|ContainerInterface */
    private $container;

    public function createGraphQLReadRateLimitAttributeListener(): void
    {
        $this->readRateLimitAttributeListener = new ReadGraphQLRateLimitAttributeListener(
            $this->container = $this->createMock(ContainerInterface::class),
            $this->rateLimitModifiers = [
                $this->createMock(RateLimitModifierInterface::class),
                $this->createMock(RateLimitModifierInterface::class),
            ],
            $this->limitDefaultValue,
            $this->periodDefaultValue
        );
    }

    public function testItDoesNotSetRateLimitIfNoAttributeProvided(): void
    {
        $this->createGraphQLReadRateLimitAttributeListener();
        $event = $this->createEvent();

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitAttributeListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    public function testItSetsGraphQLRateLimitIfAttributeProvidedWithDefaultValue(): void
    {
        $this->createGraphQLReadRateLimitAttributeListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new InputBag();
        $event = $this->createEventWithGraphQLAttribute($request, false);

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit($this->limitDefaultValue, $this->periodDefaultValue);
        $rateLimit->varyHashOn('_graphql_endpoint', 'GetObject');
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

    public function testItSetsGraphQLRateLimitIfAttributeProvidedWithCustomValue(): void
    {
        $this->createGraphQLReadRateLimitAttributeListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new InputBag();
        $event = $this->createEventWithGraphQLAttribute($request, true);

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit(10, 5);
        $rateLimit->varyHashOn('_graphql_endpoint', 'GetObject');
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

    public function testIttDoesNotSetRateLimitIfGraphQLAttributeAndEndpointNotConfigured(): void
    {
        $this->createGraphQLReadRateLimitAttributeListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new InputBag();
        $event = $this->createEventWithAnotherRequestGraphQLAttribute($request, false);

        $this->container->expects($this->never())
            ->method('has');

        $this->rateLimitModifiers[0]->expects($this->never())->method('support')->willReturn(true);
        $this->rateLimitModifiers[1]->expects($this->never())->method('support')->willReturn(false);

        $this->readRateLimitAttributeListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    /**
     * @group withoutGraphQLPackage
     */
    public function testItSetsGraphQLRateLimitIfPackageNotInstalled(): void
    {
        $this->createGraphQLReadRateLimitAttributeListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new InputBag();
        $event = $this->createEventWithGraphQLAttribute($request, false);

        $this->expectExceptionMessage('Run "composer require webonyx/graphql-php" to use @GraphQLRateLimit attribute.');
        $this->readRateLimitAttributeListener->onKernelController($event);
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

    public function createEventWithGraphQLAttribute(Request $request, bool $custom): ControllerEvent
    {
        $request->attributes->set('_controller', $custom ? FakeInvokableClassWithCustomGraphQLRateLimit::class : FakeInvokableClassWithDefaultGraphQLRateLimit::class);

        $body = <<<'GRAPHQL'
query ($id: String!) {
  GetObject(id: $id) {
    id
  }
}
GRAPHQL;

        $request->request->set('query', $body);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [new FakeClassWithRateLimit(), 'action'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    public function createEventWithAnotherRequestGraphQLAttribute(Request $request, bool $custom): ControllerEvent
    {
        $request->attributes->set('_controller', $custom ? FakeInvokableClassWithCustomGraphQLRateLimit::class : FakeInvokableClassWithDefaultGraphQLRateLimit::class);

        $body = <<<'GRAPHQL'
query ($id: String!) {
  GetAnotherObject(id: $id) {
    id
  }
}
GRAPHQL;

        $request->request->set('query', $body);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [new FakeClassWithRateLimit(), 'action'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }
}

class FakeInvokableClassWithDefaultGraphQLRateLimit
{
    #[GraphQLRateLimitAttribute(
        endpoints: [
            ['endpoint' => 'GetObject'],
        ],
    )]
    public function __invoke(): void
    {
    }
}

class FakeInvokableClassWithCustomGraphQLRateLimit
{
    #[GraphQLRateLimitAttribute(
        endpoints: [
            [
                'endpoint' => 'GetObject',
                'limit' => 10,
                'period' => 5,
            ],
        ],
    )]
    public function __invoke(): void
    {
    }
}

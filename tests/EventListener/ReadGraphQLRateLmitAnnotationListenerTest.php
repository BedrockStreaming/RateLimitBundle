<?php

namespace Bedrock\Bundle\RateLimitBundle\Tests\EventListener;

use Bedrock\Bundle\RateLimitBundle\Annotation\GraphQLRateLimit;
use Bedrock\Bundle\RateLimitBundle\Annotation\GraphQLRateLimit as GraphQLRateLimitAnnotation;
use Bedrock\Bundle\RateLimitBundle\EventListener\ReadGraphQLRateLmitAnnotationListener;
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

class ReadGraphQLRateLmitAnnotationListenerTest extends TestCase
{
    private ReadGraphQLRateLmitAnnotationListener $readRateLimitAnnotationListener;
    /** @var AnnotationReader|MockObject */
    private $annotationReader;
    /** @var array<RateLimitModifierInterface|MockObject> */
    private $rateLimitModifiers;
    private int $limitDefaultValue = 1000;
    private int $periodDefaultValue = 60;
    /** @var MockObject|ContainerInterface */
    private $container;

    public function createGraphQLReadRateLimitAnnotationListener(): void
    {
        $this->readRateLimitAnnotationListener = new ReadGraphQLRateLmitAnnotationListener(
            $this->container = $this->createMock(ContainerInterface::class),
            $this->annotationReader = $this->createMock(AnnotationReader::class),
            $this->rateLimitModifiers = [
                $this->createMock(RateLimitModifierInterface::class),
                $this->createMock(RateLimitModifierInterface::class),
            ],
            $this->limitDefaultValue,
            $this->periodDefaultValue
        );
    }

    public function testItDoesNotSetRateLimitIfNoAnnotationProvided(): void
    {
        $this->createGraphQLReadRateLimitAnnotationListener();
        $event = $this->createEvent();

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(null);

        $this->rateLimitModifiers[0]->expects($this->never())->method('support');
        $this->rateLimitModifiers[1]->expects($this->never())->method('support');

        $this->readRateLimitAnnotationListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    public function testItSetsGraphQLRateLimitIfAnnotationProvidedWithDefaultValue(): void
    {
        $this->createGraphQLReadRateLimitAnnotationListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new ParameterBag();
        $event = $this->createEventWithGraphQLAnnotation($request, false);

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(new GraphQLRateLimitAnnotation(['endpoints' => [['endpoint' => 'GetObject']]]));

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit($this->limitDefaultValue, $this->periodDefaultValue);
        $rateLimit->varyHashOn('_graphql_endpoint', 'GetObject');
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

    public function testItSetsGraphQLRateLimitIfAnnotationProvidedWithCustomValue(): void
    {
        $this->createGraphQLReadRateLimitAnnotationListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new ParameterBag();
        $event = $this->createEventWithGraphQLAnnotation($request, true);

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(new GraphQLRateLimitAnnotation(['endpoints' => [['endpoint' => 'GetObject', 'limit' => 10, 'period' => 5]]]));

        $this->rateLimitModifiers[0]->expects($this->once())->method('support')->willReturn(true);
        $rateLimit = new RateLimit(10, 5);
        $rateLimit->varyHashOn('_graphql_endpoint', 'GetObject');
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

    public function testIttDoesNotSetRateLimitIfGraphQLAnnotationAndEndpointNotConfigured(): void
    {
        $this->createGraphQLReadRateLimitAnnotationListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new ParameterBag();
        $event = $this->createEventWithGraphQLAnnotation($request, false);

        $this->container->expects($this->never())
            ->method('has');

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(new GraphQLRateLimitAnnotation(['endpoints' => [['endpoint' => 'GetAnotherObject']]]));

        $this->rateLimitModifiers[0]->expects($this->never())->method('support')->willReturn(true);
        $this->rateLimitModifiers[1]->expects($this->never())->method('support')->willReturn(false);

        $this->readRateLimitAnnotationListener->onKernelController($event);
        $this->assertFalse($event->getRequest()->attributes->has('_rate_limit'));
    }

    /**
     * @group withoutGraphQLPackage
     */
    public function testItSetsGraphQLRateLimitIfPackageNotInstalled(): void
    {
        $this->createGraphQLReadRateLimitAnnotationListener();
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag();
        $request->request = new ParameterBag();
        $event = $this->createEventWithGraphQLAnnotation($request, false);

        $this->annotationReader->expects($this->once())->method('getMethodAnnotation')->willReturn(new GraphQLRateLimitAnnotation(['endpoints' => [['endpoint' => 'GetObject']]]));

        $this->expectExceptionMessage('Run "composer require webonyx/graphql-php" to use @GraphQLRateLimit annotation.');
        $this->readRateLimitAnnotationListener->onKernelController($event);
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

    public function createEventWithGraphQLAnnotation(Request $request, bool $custom): ControllerEvent
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
}

class FakeInvokableClassWithDefaultGraphQLRateLimit
{
    /**
     * @GraphQLRateLimit(
     *     {
     *         'endpoints' = {
     *             {'endpoint' = 'GetObject'}
     *     }
     * )
     */
    public function __invoke(): void
    {
    }
}

class FakeInvokableClassWithCustomGraphQLRateLimit
{
    /**
     * @GraphQLRateLimit(
     *     {
     *     'endpoints' = {
     *        { 'endpoint' = 'GetObject', 'limit' = 10, 'period' = 5}
     *     }
     * )
     */
    public function __invoke(): void
    {
    }
}

<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\EventListener;

use Bedrock\Bundle\RateLimitBundle\Annotation\RateLimit as RateLimitAnnotation;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use Doctrine\Common\Annotations\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class ReadRateLimitAnnotationListener implements EventSubscriberInterface
{
    private Reader $annotationReader;
    /** @var iterable<RateLimitModifierInterface> */
    private $rateLimitModifiers;
    private int $limit;
    private int $period;
    private bool $limitByRoute;
    private ContainerInterface $container;

    /**
     * @param RateLimitModifierInterface[] $rateLimitModifiers
     */
    public function __construct(ContainerInterface $container, Reader $annotationReader, iterable $rateLimitModifiers, int $limit, int $period, bool $limitByRoute)
    {
        foreach ($rateLimitModifiers as $rateLimitModifier) {
            if (!($rateLimitModifier instanceof RateLimitModifierInterface)) {
                throw new \InvalidArgumentException(('$rateLimitModifiers must be instance of '.RateLimitModifierInterface::class));
            }
        }

        $this->annotationReader = $annotationReader;
        $this->rateLimitModifiers = $rateLimitModifiers;
        $this->limit = $limit;
        $this->period = $period;
        $this->limitByRoute = $limitByRoute;
        $this->container = $container;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        // retrieve controller and method from request
        $controllerAttribute = $request->attributes->get('_controller', null);
        if (null === $controllerAttribute || !is_string($controllerAttribute)) {
            return;
        }
        $controllerAttributeParts = explode('::', $controllerAttribute);

        if (!class_exists($controllerAttributeParts[0])) {
            // If controller is passed with alias until class name
            $serviceIdAttribteParts = explode(':', $controllerAttributeParts[0]);
            if (!$this->container->has($serviceIdAttribteParts[0])) {
                throw new \InvalidArgumentException('Parameter _controller from request : "'.$controllerAttribute.'" do not contains a valid class name');
            }
            $controllerAttributeParts[0] = $this->container->get($serviceIdAttribteParts[0]);
            $controllerAttributeParts[1] = $serviceIdAttribteParts[1] ?? '';
        }
        $reflection = new \ReflectionClass($controllerAttributeParts[0]);
        $annotation = $this->annotationReader->getMethodAnnotation($reflection->getMethod((string) ($controllerAttributeParts[1] ?? '__invoke')), RateLimitAnnotation::class);

        if (!$annotation instanceof RateLimitAnnotation) {
            return;
        }

        if ($this->limitByRoute) {
            $rateLimit = new RateLimit(
                $annotation->getLimit() ?? $this->limit,
                $annotation->getPeriod() ?? $this->period
            );

            $rateLimit->varyHashOn('_route', $request->attributes->get('_route'));
        } else {
            $rateLimit = new RateLimit(
                $this->limit,
                $this->period
            );
        }

        foreach ($this->rateLimitModifiers as $hashKeyVarier) {
            if ($hashKeyVarier->support($request)) {
                $hashKeyVarier->modifyRateLimit($request, $rateLimit);
            }
        }
        $request->attributes->set('_rate_limit', $rateLimit);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ControllerEvent::class => 'onKernelController',
        ];
    }
}

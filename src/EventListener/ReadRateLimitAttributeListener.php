<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\EventListener;

use Bedrock\Bundle\RateLimitBundle\Attribute\RateLimit as RateLimitAttribute;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class ReadRateLimitAttributeListener implements EventSubscriberInterface
{
    /** @var iterable<RateLimitModifierInterface> */
    private iterable $rateLimitModifiers;

    /**
     * @param RateLimitModifierInterface[] $rateLimitModifiers
     */
    public function __construct(private ContainerInterface $container, iterable $rateLimitModifiers, private int $limit, private int $period, private bool $limitByRoute)
    {
        foreach ($rateLimitModifiers as $rateLimitModifier) {
            if (!($rateLimitModifier instanceof RateLimitModifierInterface)) {
                throw new \InvalidArgumentException('$rateLimitModifiers must be instance of '.RateLimitModifierInterface::class);
            }
        }
        $this->rateLimitModifiers = $rateLimitModifiers;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        // retrieve controller and method from request
        $controllerAttribute = $request->attributes->get('_controller', null);
        if (null === $controllerAttribute || !is_string($controllerAttribute)) {
            return;
        }
        // services alias can be used with 'service.alias:functionName' or 'service.alias::functionName'
        $controllerAttributeParts = explode(':', str_replace('::', ':', $controllerAttribute));
        $controllerName = $controllerAttributeParts[0] ?? '';
        $methodName = $controllerAttributeParts[1] ?? null;

        if (!class_exists($controllerName)) {
            // If controller attribute is an alias instead of a class name
            if (null === ($controllerName = $this->container->get($controllerAttributeParts[0]))) {
                throw new \InvalidArgumentException('Parameter _controller from request : "'.$controllerAttribute.'" do not contains a valid class name');
            }
        }
        $reflection = new \ReflectionClass($controllerName);
        $attributes = $reflection->getMethod((string) ($methodName ?? '__invoke'))->getAttributes(RateLimitAttribute::class);

        if (count($attributes) > 1) {
            throw new \InvalidArgumentException('Unexpected value');
        }

        /** @var ?\ReflectionAttribute $attribute */
        $attribute = array_shift($attributes);

        if (null === $attribute) {
            return;
        }

        /** @var RateLimitAttribute $rateLimitAttribute */
        $rateLimitAttribute = $attribute->newInstance();

        $rateLimit = new RateLimit(
            $this->limit,
            $this->period
        );

        if ($this->limitByRoute) {
            $rateLimit = new RateLimit(
                $rateLimitAttribute->getLimit() ?? $this->limit,
                $rateLimitAttribute->getPeriod() ?? $this->period
            );

            /** @var string $route */
            $route = $request->attributes->get('_route');
            $rateLimit->varyHashOn('_route', $route);
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

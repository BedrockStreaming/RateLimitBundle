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

class ReadRateLimitConfigurationListener implements EventSubscriberInterface
{
    /** @var iterable<RateLimitModifierInterface> */
    private $rateLimitModifiers;
    private int $limit;
    private int $period;
    private array $routes;

    /**
     * @param RateLimitModifierInterface[] $rateLimitModifiers
     */
    public function __construct(iterable $rateLimitModifiers, int $limit, int $period, array $routes)
    {
        foreach ($rateLimitModifiers as $rateLimitModifier) {
            if (!($rateLimitModifier instanceof RateLimitModifierInterface)) {
                throw new \InvalidArgumentException(('$rateLimitModifiers must be instance of '.RateLimitModifierInterface::class));
            }
        }

        $this->rateLimitModifiers = $rateLimitModifiers;
        $this->limit = $limit;
        $this->period = $period;
        $this->routes = $routes;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route', null);

        if (!array_key_exists($routeName, $this->routes)) {
            return;
        }

        $rateLimit = new RateLimit(
            $this->routes[$routeName]['limit'] ?? $this->limit,
            $this->routes[$routeName]['period'] ?? $this->period
        );

        $rateLimit->varyHashOn('_route', $routeName);

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

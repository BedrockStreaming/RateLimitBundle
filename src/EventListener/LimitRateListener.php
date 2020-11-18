<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\EventListener;

use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\Storage\ManuallyResetableRateLimitStorageInterface;
use Bedrock\Bundle\RateLimitBundle\Storage\RateLimitStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

class LimitRateListener implements EventSubscriberInterface
{
    private RateLimitStorageInterface $storage;
    private bool $displayHeaders;

    public function __construct(RateLimitStorageInterface $storage, bool $displayHeaders)
    {
        $this->storage = $storage;
        $this->displayHeaders = $displayHeaders;
    }

    public function onKernelController(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->attributes->has('_rate_limit')) {
            return;
        }

        $rateLimit = $request->attributes->get('_rate_limit');

        if (!$rateLimit instanceof RateLimit) {
            throw new \InvalidArgumentException(sprintf('Request attribute "_rate_limit" should be of type "%s". "%s" given.', RateLimit::class, \is_object($rateLimit) ? \get_class($rateLimit) : \gettype($rateLimit)));
        }

        $storedRateLimit = $this->storage->getStoredRateLimit($rateLimit);

        if (null !== $storedRateLimit
            && $storedRateLimit->isOutdated()) {
            if ($this->storage instanceof ManuallyResetableRateLimitStorageInterface) {
                $this->storage->resetRateLimit($rateLimit);
            }

            $storedRateLimit = null;
        }

        if (null !== $storedRateLimit && $storedRateLimit->getHits() >= $rateLimit->getLimit()) {
            $displayHeaders = $this->displayHeaders;
            $event->setController(
                static function () use ($displayHeaders, $storedRateLimit) {
                    return new JsonResponse(
                        $displayHeaders ? $storedRateLimit->getLimitReachedOutput() : Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS],
                        Response::HTTP_TOO_MANY_REQUESTS
                    );
                }
            );
        }

        if (null === $storedRateLimit) {
            $storedRateLimit = $this->storage->storeRateLimit($rateLimit);
        } else {
            $storedRateLimit = $this->storage->incrementHits($storedRateLimit);
        }

        $request->attributes->set('_stored_rate_limit', $storedRateLimit);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ControllerArgumentsEvent::class => 'onKernelController',
        ];
    }
}

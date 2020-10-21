<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\EventListener;

use Bedrock\Bundle\RateLimitBundle\Model\StoredRateLimit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AddRateLimitHeadersListener implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->attributes->has('_stored_rate_limit')) {
            return;
        }

        $storedRateLimit = $request->attributes->get('_stored_rate_limit');

        if (!$storedRateLimit instanceof StoredRateLimit) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('x-rate-limit', (string) $storedRateLimit->getLimit());
        $response->headers->set('x-rate-limit-hits', (string) $storedRateLimit->getHits());
        $response->headers->set('x-rate-limit-until', $storedRateLimit->getValidUntil()->format('c'));
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => 'onKernelResponse',
        ];
    }
}

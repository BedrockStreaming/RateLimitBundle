<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\EventListener;

use Bedrock\Bundle\RateLimitBundle\Annotation\GraphQLRateLimit as GraphQLRateLimitAnnotation;
use Bedrock\Bundle\RateLimitBundle\Annotation\RateLimit as RateLimitAnnotation;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use Doctrine\Common\Annotations\Reader;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
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
        $annotation = $this->annotationReader->getMethodAnnotation($reflection->getMethod((string) ($methodName ?? '__invoke')), RateLimitAnnotation::class) ?? $this->annotationReader->getMethodAnnotation($reflection->getMethod((string) ($methodName ?? '__invoke')), GraphQLRateLimitAnnotation::class);

        if (!$annotation instanceof RateLimitAnnotation && !$annotation instanceof GraphQLRateLimitAnnotation) {
            return;
        }

        if ($annotation instanceof RateLimitAnnotation) {
            $rateLimit = new RateLimit(
                $this->limit,
                $this->period
            );

            if ($this->limitByRoute) {
                $rateLimit = new RateLimit(
                    $annotation->getLimit() ?? $this->limit,
                    $annotation->getPeriod() ?? $this->period
                );

                $rateLimit->varyHashOn('_route', $request->attributes->get('_route'));
            }
        }

        if ($annotation instanceof GraphQLRateLimitAnnotation) {
            if (!class_exists('GraphQL\Language\Parser')) {
                throw new \Exception('Run "composer require webonyx/graphql-php" to use @GraphQLRateLimit annotation.');
            }

            $endpoint = $this->extractQueryName($request->request->get('query'));

            foreach ($annotation->getEndpointConfigurations() as $graphQLEndpointConfiguration) {
                if ($endpoint === $graphQLEndpointConfiguration->getEndpoint()) {
                    $rateLimit = new RateLimit(
                        $graphQLEndpointConfiguration->getLimit() ?? $this->limit,
                        $graphQLEndpointConfiguration->getPeriod() ?? $this->period
                    );
                    $rateLimit->varyHashOn('_graphql_endpoint', $endpoint);
                    break;
                }
            }
            if (!isset($rateLimit)) {
                return;
            }
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

    /**
     * @param Source|string $query
     */
    public function extractQueryName($query): string
    {
        $parsedQuery = Parser::parse($query);
        /** @var OperationDefinitionNode $item */
        foreach ($parsedQuery->definitions->getIterator() as $item) {
            /* @phpstan-ignore-next-line */
            return (string) $item->selectionSet->selections[0]->name->value;
        }

        throw new QueryExtractionException('Unable to extract query');
    }
}

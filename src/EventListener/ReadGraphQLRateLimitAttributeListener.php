<?php

namespace Bedrock\Bundle\RateLimitBundle\EventListener;

use Bedrock\Bundle\RateLimitBundle\Attribute\GraphQLRateLimit as GraphQLRateLimitAttribute;
use Bedrock\Bundle\RateLimitBundle\Model\RateLimit;
use Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RateLimitModifierInterface;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class ReadGraphQLRateLimitAttributeListener implements EventSubscriberInterface
{
    /** @var iterable<RateLimitModifierInterface> */
    private iterable $rateLimitModifiers;

    /**
     * @param RateLimitModifierInterface[] $rateLimitModifiers
     */
    public function __construct(private ContainerInterface $container, iterable $rateLimitModifiers, private int $limit, private int $period)
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
        $attributes = $reflection->getMethod((string) ($methodName ?? '__invoke'))->getAttributes(GraphQLRateLimitAttribute::class);

        if (count($attributes) > 1) {
            throw new \InvalidArgumentException('Unexpected value');
        }

        /** @var ?\ReflectionAttribute $attribute */
        $attribute = array_shift($attributes);

        if (null === $attribute) {
            return;
        }

        /** @var GraphQLRateLimitAttribute $rateLimitAttribute */
        $rateLimitAttribute = $attribute->newInstance();

        if (!class_exists(\GraphQL\Language\Parser::class)) {
            throw new \Exception('Run "composer require webonyx/graphql-php" to use @GraphQLRateLimit attribute.');
        }

        $endpoint = $this->extractQueryName($request->request->get('query'));

        foreach ($rateLimitAttribute->getEndpointConfigurations() as $graphQLEndpointConfiguration) {
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
     * @param string|int|float|bool|null $query
     */
    public function extractQueryName($query): string
    {
        /** @var Source $query */
        $parsedQuery = Parser::parse($query);
        /** @var OperationDefinitionNode $item */
        foreach ($parsedQuery->definitions->getIterator() as $item) {
            /* @phpstan-ignore-next-line */
            return (string) $item->selectionSet->selections[0]->name->value;
        }

        throw new QueryExtractionException('Unable to extract query');
    }
}

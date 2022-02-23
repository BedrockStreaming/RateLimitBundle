# RateLimitBundle
This bundle provides an easy way to protect your project by limiting access to your controllers.

## Install the bundle
```bash
composer require bedrockstreaming/rate-limit-bundle
```

Update your _config/bundles.php_ file to add the bundle for all env
```php
<?php

return [
    ...
    Bedrock\Bundle\RateLimitBundle\RateLimitBundle::class => ['all' => true],
    ...
];
```

### Configure the bundle
Add the _config/packages/bedrock_rate_limit.yaml_ file with the following data.
```yaml
bedrock_rate_limit:
    limit: 25 # 1000 requests by default
    period: 600 # 60 seconds by default
    limit_by_route: true|false # false by default
    debug: true|false # false by default
```
By default, the limitation is common to all routes annotated `@RateLimit()`. 
For example, if you keep the default configuration and you configure the `@RateLimit()` annotation in 2 routes. Limit will shared between this 2 routes, if user consume all authorized calls on the first route, the second route couldn't be called.
If you swicth `limit_by_route` to true, users will be allowed to reach the limit on each route annotated.

`@GraphQLRateLimit()`annotation allows you to rate limit by graphQL query or mutation.
/!\ To use this annotation, you will need to install suggested package.

If you switch `debug` to true, a more detailed response will be displayed. This can be usefull to debug your limitations. Do not use this in prod environment.

### How to know if rate limit is reached
The bundle will add 4 headers to the response :
* `x-rate-limit` : display how many hits can made until the limit is reached
* `x-rate-limit-hits` : display how many hits has been done since the beginning of the period 
* `x-rate-limit-until` : display the date of the end of the limit in ISO 8601 format  
* `retry-after` : display how many remaining seconds the limit is applied

### Configure your storage 
You must tell Symfony which storage implementation you want to use.

Update your _config/services.yml_ like this:

```yaml
    ...
    Bedrock\Bundle\RateLimitBundle\Storage\RateLimitStorageInterface: '@Bedrock\Bundle\RateLimitBundle\Storage\RateLimitInMemoryStorage'
    ...
``` 

By default, only `RateLimitInMemory` is provided. But feel free to create your own by implementing `RateLimitStorageInterface` or `ManuallyResetableRateLimitStorageInterface`.
If your database has a TTL system (like Redis), you can implement only `RateLimitStorageInterface`. Otherwhise you must implement also `ManuallyResetableRateLimitStorageInterface` to manually delete rate limit in your database. 

### Configure your modifiers
Modifiers are a way to customize the rate limit.

This bundle provides 2 modifiers: 
* `HttpMethodRateLimitModifier` limits the requests by `http_method`.
* `RequestAttributeRateLimitModifier` limits the requests by attributes value (taken from the `$request->attributes` Symfony's bag).

Update your _config/services.yml_ like this:

```yaml
    ...
    Bedrock\Bundle\RateLimitBundle\RateLimitModifier\HttpMethodRateLimitModifier:
        tags: [ 'rate_limit.modifiers' ]   

    Bedrock\Bundle\RateLimitBundle\RateLimitModifier\RequestAttributeRateLimitModifier:
            arguments:
                $attributeName: 'myRequestAttribute'
            tags: [ 'rate_limit.modifiers' ]
 
    ...
``` 

You can also create your own rate limit modifier by implementing `RateLimitModifierInterface` and tagging your service accordingly.

### Configure your routes
Add the `@RateLimit()` annotation to your controller methods (by default, the limit will be 1000 requests per minute).
This annotation accepts parameters to customize the rate limit. The following example shows how to limit requests on a route at the rate of 10 requests max every 2 minutes.
:warning: This customization only works if the `limit_by_route` parameter is `true`

```php
/**
* @RateLimit(
*     limit=10,
*     period=120
* )
*/
```

To rate limit your graphQL API, add the `@GraphQLRateLimit()` annotation to your graphQL controller.
This annotation requires a list of endpoints and accepts parameters to customize the rate limit. The following example shows how to limit requests on an endpoint at the rate of 10 requests max every 2 minutes and on default limitations.

```php
/**
* @GraphQLRateLimit(
*     endpoints={
*        {"endpoint"="GetMyQuery", "limit"=10, "period"=120},
*        {"endpoint"="EditMyMutation"},
*     }
* )
*/
```

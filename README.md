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
    display_headers: true|false # false by default
```
By default, the limitation is common to all routes annotated `@RateLimit()`. 
For example, if you keep the default configuration and you configure the `@RateLimit()` annotation in 2 routes. Limit will shared between this 2 routes, if user consume all authorized calls on the first route, the second route couldn't be called.
If you swicth `limit_by_route` to true, users will be allowed to reach the limit on each route annotated.

`@GraphQLRateLimit()`annotation allows you to rate limit by graphQL query or mutation.
/!\ To use this annotation, you will need to install suggested package.

If you switch `display_headers` to true, 3 headers will be added `x-rate-limit`, `x-rate-limit-hits`, `x-rate-limit-untils` to your responses. This can be usefull to debug your limitations.
`display_headers` is used to display a verbose return if limit is reached.
 
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

#### With annotations

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

#### In YAML

You also may add your rate limits in configuration files (Yaml) with the route name. If `period` or `limit` is not
defined for a route, the bundle will take the common option.

```yaml
bedrock_rate_limit:
    limit: 1000
    period: 60

    routes:
        get_foobar:
            limit: 500
            period: 10
        post_foobar:
            period: 10
```

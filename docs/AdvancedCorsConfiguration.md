# Advanced CORS configuration

### CORS strategy

When doing bootstrap configuration for **[oasis/http]**, the `cors` section consists of an array of CORS strategies. Each strategy can either be an `Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingStrategy` object, or more often, an array complying with the following rule:

Name        | Type                          | Description
---         | ---                           | ---
pattern     | string &#124; RequestMatcher  | pattern to match the request
origins     | string &#124; array           | allowed origins
headers     | array                         | allowed custom request headers
headers_exposed | array                     | allowed response headers that can be exposed in browser
max_age     | integer                       | max age of preflight request, default to 86400 seconds
credentials_allowed | bool                  | whether credentials can be sent alone with the request (i.e. cookies)

If an array is given, a `CrossOriginResourceSharingStrategy` will be automatically generated using that array.

### Custom Strategy

In practice, we may want to implement more complicate strategy. In this case, extending the `CrossOriginResourceSharingStrategy` class is a better solution.

For instance, if an application would like to restrict origin depending on sender's identity, it can override the `isOriginAllowed()` method:

```php
<?php

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingStrategy;

class CustomCorsStrategy extends CrossOriginResourceSharingStrategy
{
    public function isOriginAllowed($origin)
    {
        if ($this->request && $this->request->attributes->has('sender')) {
            $this->originsAllowed = $sender->getOriginsAllowed();
        }

        return parent::isOriginAllowed($origin);
    }
}

```

[oasis/http]: ../README.md

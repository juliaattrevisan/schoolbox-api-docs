# HTTP Adapter

HTTP requests from Schoolbox are sent using its adapter, which abstracts around
the configuration required to make HTTP connections to external servers (e.g.
configuration of proxy servers).

The adapter uses the [Guzzle](https://docs.guzzlephp.org/en/stable/) PHP HTTP
client.

## Instantiation

```php
/** @var \Psr\Container\ContainerInterface $di */

/** @var \GuzzleHttp\Client $httpClient */
$httpClient = $di->get('httpClient');
```

## Usage

```php
/** @var \GuzzleHttp\Client $httpClient */
$response     = $httpClient->get($url);
$responseBody = $response->getBody();
```

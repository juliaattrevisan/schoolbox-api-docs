<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing;

use Closure;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanStatus;
use Throwable;

/**
 * `\Sentry\Tracing\GuzzleTracingMiddleware`, adapted to write to the default
 * trace container for later writing.
 */
class GuzzleTracingMiddleware
{
    /**
     * Create the middleware function.
     *
     * @param HubInterface $hub    The Sentry hub.
     *                             Required for determining whether to attach
     *                             tracing headers to the outgoing request.
     * @param Tracer       $tracer The trace container: stack changes are added
     *                             to this.
     * @return Closure(RequestInterface, array)
     */
    public static function trace(HubInterface $hub, Tracer $tracer): Closure
    {
        return static function (callable $handler) use ($hub, $tracer): Closure {
            return static function (RequestInterface $request, array $options) use ($hub, $tracer, $handler) {
                // Add tracing headers if required
                if (self::shouldAttachTracingHeaders($hub->getClient(), $request)) {
                    $request = $request
                        ->withHeader('sentry-trace', $tracer->getTraceparent())
                        ->withHeader('traceparent', $tracer->getW3CTraceparent())
                        ->withHeader('baggage', $tracer->getBaggage());
                }

                // Build span/breadcrumb request data
                $spanAndBreadcrumbData = [
                    'http.request.method'    => $request->getMethod(),
                    'http.request.body.size' => $request->getBody()->getSize(),
                ];
                if ($request->getUri()->getQuery() !== '') {
                    $spanAndBreadcrumbData['http.query'] = $request->getUri()->getQuery();
                }
                if ($request->getUri()->getFragment() !== '') {
                    $spanAndBreadcrumbData['http.fragment'] = $request->getUri()->getFragment();
                }

                $partialUri = Uri::fromParts([
                    'scheme' => $request->getUri()->getScheme(),
                    'host'   => $request->getUri()->getHost(),
                    'port'   => $request->getUri()->getPort(),
                    'path'   => $request->getUri()->getPath(),
                ]);

                $startTime              = microtime(true);
                $handlerPromiseCallback = static function ($responseOrException) use ($startTime, $tracer, $spanAndBreadcrumbData, $partialUri) {
                    // Add response details to span/breadcrumb data
                    $response = null;
                    if ($responseOrException instanceof ResponseInterface) {
                        $response = $responseOrException;
                    } elseif ($responseOrException instanceof GuzzleRequestException) {
                        $response = $responseOrException->getResponse();
                    }
                    if ($response) {
                        $spanAndBreadcrumbData['http.response.body.size'] = $response->getBody()->getSize();
                        $spanAndBreadcrumbData['http.response.status_code'] = $response->getStatusCode();
                        $status = SpanStatus::createFromHttpStatusCode($response->getStatusCode());
                    } else {
                        $status = SpanStatus::internalError();
                    }

                    // Start the span at the prescribed start time
                    $tracer->startSpan(
                        $spanAndBreadcrumbData['http.request.method'] . ' ' . $partialUri,
                        'http.client',
                        'auto.http.guzzle',
                        $startTime,
                        $spanAndBreadcrumbData,
                    );

                    // Finish the span
                    $finishTime = microtime(true);
                    $tracer->finishSpan($finishTime, $status);

                    // Add a breadcrumb where the span finished
                    $tracer->addBreadcrumb(
                        Breadcrumb::LEVEL_INFO,
                        Breadcrumb::TYPE_HTTP,
                        'http',
                        null,
                        array_merge(
                            ['url' => (string)$partialUri],
                            $spanAndBreadcrumbData,
                        ),
                        $finishTime,
                    );

                    // Return from the promise
                    if ($responseOrException instanceof Throwable) {
                        throw $responseOrException;
                    }
                    return $responseOrException;
                };

                return $handler($request, $options)->then($handlerPromiseCallback, $handlerPromiseCallback);
            };
        };
    }

    /**
     * Should Sentry's tracing headers be attached to the outgoing request, so
     * that Sentry may include that request's transaction in the same trace as
     * this request's?
     *
     * @param ClientInterface|null $client
     * @param RequestInterface     $request
     * @return bool
     */
    private static function shouldAttachTracingHeaders(?ClientInterface $client, RequestInterface $request): bool
    {
        if (!$client) {
            return false;
        }

        $sdkOptions = $client->getOptions();
        return $sdkOptions->getTracePropagationTargets() === null
            || in_array($request->getUri()->getHost(), $sdkOptions->getTracePropagationTargets());
    }
}

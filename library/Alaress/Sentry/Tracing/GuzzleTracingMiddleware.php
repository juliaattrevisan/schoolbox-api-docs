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
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;

use Throwable;

use function Sentry\getBaggage;
use function Sentry\getTraceparent;
use function Sentry\getW3CTraceparent;

/**
 * `\Sentry\Tracing\GuzzleTracingMiddleware`, adapted to write to the default
 * trace container for later writing.
 */
class GuzzleTracingMiddleware
{
    /**
     * Create the middleware function for
     *
     * @param HubInterface|null $hub
     * @return Closure(RequestInterface, array)
     */
    public static function trace(?HubInterface $hub = null, ?Tracer $tracer = null): Closure
    {
        return static function (callable $handler) use ($hub, $tracer): Closure {
            return static function (RequestInterface $request, array $options) use ($hub, $tracer, $handler) {
                $tracer ??= Tracer::getDefaultTrace();
                $hub        = $hub ?? SentrySdk::getCurrentHub();
                $client     = $hub->getClient();
//                $parentSpan = $hub->getSpan();

                $partialUri = Uri::fromParts([
                    'scheme' => $request->getUri()->getScheme(),
                    'host'   => $request->getUri()->getHost(),
                    'port'   => $request->getUri()->getPort(),
                    'path'   => $request->getUri()->getPath(),
                ]);

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

                $startTime = microtime(true);
//                $tracer->startSpan(
//                    $request->getMethod() . ' ' . $partialUri,
//                    'http.client',
//                    'auto.http.guzzle',
//                    microtime(true),
//                    $spanAndBreadcrumbData,
//                );

//                $childSpan = null;
//
//                if ($parentSpan !== null && $parentSpan->getSampled()) {
//                    $spanContext = new SpanContext();
//                    $spanContext->setOp('http.client');
//                    $spanContext->setData($spanAndBreadcrumbData);
//                    $spanContext->setOrigin('auto.http.guzzle');
//                    $spanContext->setDescription($request->getMethod() . ' ' . $partialUri);
//
//                    $childSpan = $parentSpan->startChild($spanContext);
//                    $tracer->startSpan(
//                        $request->getMethod() . ' ' . $partialUri,
//                        'http.client',
//                        'auto.http.guzzle',
//                        microtime(true),
//                        $spanAndBreadcrumbData,
//                    );
//                }

                if (self::shouldAttachTracingHeaders($client, $request)) {
                    $request = $request
                        ->withHeader('sentry-trace', getTraceparent())
                        ->withHeader('traceparent', getW3CTraceparent())
                        ->withHeader('baggage', getBaggage());
                }

                $handlerPromiseCallback = static function ($responseOrException) use ($hub, $spanAndBreadcrumbData, $childSpan, $partialUri, $startTime, $tracer, $request) {
//                    if ($childSpan !== null) {
//                        // We finish the span (which means setting the span end timestamp) first to ensure the measured time
//                        // the span spans is as close to only the HTTP request time and do the data collection afterwards
//                        $childSpan->finish();
//                    }

                    $response = null;

                    if ($responseOrException instanceof ResponseInterface) {
                        $response = $responseOrException;
                    } elseif ($responseOrException instanceof GuzzleRequestException) {
                        $response = $responseOrException->getResponse();
                    }

                    if ($response !== null) {
                        $spanAndBreadcrumbData['http.response.body.size'] = $response->getBody()->getSize();
                        $spanAndBreadcrumbData['http.response.status_code'] = $response->getStatusCode();
                    }

                    $tracer->startSpan(
                        $request->getMethod() . ' ' . $partialUri,
                        'http.client',
                        'auto.http.guzzle',
                        $startTime,
                        $spanAndBreadcrumbData,
                    );

                    $status = $response
                        ? SpanStatus::createFromHttpStatusCode($response->getStatusCode())
                        : SpanStatus::internalError();
                    $tracer->finishSpan(microtime(true), $status);

                    $hub->addBreadcrumb(
                        new Breadcrumb(
                            Breadcrumb::LEVEL_INFO,
                            Breadcrumb::TYPE_HTTP,
                            'http',
                            null,
                            array_merge(
                                ['url' => (string)$partialUri],
                                $spanAndBreadcrumbData,
                            ),
                        )
                    );

                    if ($responseOrException instanceof Throwable) {
                        throw $responseOrException;
                    }

                    return $responseOrException;
                };

                return $handler($request, $options)->then($handlerPromiseCallback, $handlerPromiseCallback);
            };
        };
    }

    private static function shouldAttachTracingHeaders(?ClientInterface $client, RequestInterface $request): bool
    {
        if ($client === null) {
            return false;
        }

        $sdkOptions = $client->getOptions();

        // Check if the request destination is allow listed in the trace_propagation_targets option.
        return $sdkOptions->getTracePropagationTargets() === null
            || \in_array($request->getUri()->getHost(), $sdkOptions->getTracePropagationTargets());
    }
}

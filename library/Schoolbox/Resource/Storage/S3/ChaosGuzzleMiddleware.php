<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP handler that simulates network-level EOF errors for testing
 *
 * This handler randomly truncates HTTP response streams to simulate
 * premature connection termination that can occur with CDNs like Cloudflare.
 * This provides more realistic testing than application-level EOF simulation.
 */
class ChaosGuzzleMiddleware
{
    private int $chancePercent;
    private int $minBytesBeforeChaos;
    private $defaultHandler;

    public function __construct(int $chancePercent = 10, int $minBytesBeforeChaos = 1024)
    {
        $this->chancePercent = $chancePercent;
        $this->minBytesBeforeChaos = $minBytesBeforeChaos;
        $this->defaultHandler = \GuzzleHttp\HandlerStack::create();
    }

    public function __invoke(RequestInterface $request, array $options = [])
    {
        error_log("ChaosGuzzleMiddleware: Handling {$request->getMethod()} request to {$request->getUri()}");

        $promise = $this->defaultHandler->__invoke($request, $options);

        return $promise->then(
            function (ResponseInterface $response) use ($request) {
                // Only inject chaos for S3 GET requests (downloads)
                if ($request->getMethod() !== 'GET') {
                    error_log("ChaosGuzzleMiddleware: Skipping non-GET request ({$request->getMethod()})");
                    return $response;
                }

                $body = $response->getBody();
                $size = $body->getSize();
                error_log("ChaosGuzzleMiddleware: GET request response size: " . ($size ?? 'unknown'));

                // Check if we should inject chaos
                $chaosRoll = random_int(1, 100);
                if ($chaosRoll > $this->chancePercent) {
                    error_log("ChaosGuzzleMiddleware: No chaos injection (rolled {$chaosRoll}, need <= {$this->chancePercent})");
                    return $response;
                }

                // Only inject chaos for responses larger than minimum threshold
                // Allow unknown size (streaming responses) but skip definitely small responses
                if ($size !== null && $size <= $this->minBytesBeforeChaos) {
                    error_log("ChaosGuzzleMiddleware: Skipping chaos - response too small ({$size} <= {$this->minBytesBeforeChaos})");
                    return $response;
                }

                // Create a truncated stream that simulates premature EOF
                $truncatedStream = new ChaosStream($body, $this->minBytesBeforeChaos, $size);
                error_log("ChaosGuzzleMiddleware: CHAOS INJECTED! Truncating response from {$size} bytes to ~{$this->minBytesBeforeChaos}-" . (int)($size * 0.8) . " bytes");

                return $response->withBody($truncatedStream);
            }
        );
    }
}

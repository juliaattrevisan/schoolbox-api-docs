<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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

/**
 * Stream wrapper that truncates content to simulate network EOF
 */
class ChaosStream implements StreamInterface
{
    private StreamInterface $innerStream;
    private int $truncateAt;
    private ?int $originalSize;
    private int $bytesRead = 0;

    public function __construct(StreamInterface $innerStream, int $minBytes, ?int $originalSize)
    {
        $this->innerStream = $innerStream;
        $this->originalSize = $originalSize;

        // For unknown sizes, truncate after a random amount between min and a reasonable max
        if ($originalSize === null) {
            $maxTruncate = random_int($minBytes * 2, $minBytes * 10); // 2x to 10x the minimum
            $this->truncateAt = random_int($minBytes, $maxTruncate);
            error_log("ChaosStream: Created for unknown size stream, truncating at {$this->truncateAt} bytes");
        } else {
            // Randomly choose truncation point between min and 80% of file size
            $maxTruncate = (int)($originalSize * 0.8);
            $this->truncateAt = random_int($minBytes, max($minBytes, $maxTruncate));
            error_log("ChaosStream: Created with truncation at {$this->truncateAt} bytes (original: {$originalSize} bytes)");
        }
    }

    public function read($length): string
    {
        if ($this->bytesRead >= $this->truncateAt) {
            // Simulate EOF by returning empty string
            error_log("ChaosStream: Returning EOF at {$this->bytesRead} bytes (truncated at {$this->truncateAt})");
            return '';
        }

        $remainingBeforeTruncate = $this->truncateAt - $this->bytesRead;
        $readLength = min($length, $remainingBeforeTruncate);

        $data = $this->innerStream->read($readLength);
        $this->bytesRead += strlen($data);

        if ($this->bytesRead >= $this->truncateAt) {
            error_log("ChaosStream: Hit truncation point after reading {$this->bytesRead} bytes");
        }

        return $data;
    }

    public function eof(): bool
    {
        return $this->bytesRead >= $this->truncateAt || $this->innerStream->eof();
    }

    public function getSize(): ?int
    {
        // Return original size so callers think they're getting the full file
        // For unknown sizes, return null to maintain the original behavior
        return $this->originalSize;
    }

    // Delegate all other methods to the inner stream
    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        $this->innerStream->close();
    }

    public function detach()
    {
        return $this->innerStream->detach();
    }

    public function tell(): int
    {
        return $this->bytesRead;
    }

    public function rewind(): void
    {
        $this->bytesRead = 0;
        $this->innerStream->rewind();
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        $result = $this->innerStream->seek($offset, $whence);
        $this->bytesRead = $this->innerStream->tell();
    }

    public function isSeekable(): bool
    {
        return $this->innerStream->isSeekable();
    }

    public function write($string): int
    {
        throw new \RuntimeException('ChaosStream is read-only');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function isReadable(): bool
    {
        return $this->innerStream->isReadable();
    }

    public function getContents(): string
    {
        $contents = '';
        while (!$this->eof()) {
            $contents .= $this->read(8192);
        }
        return $contents;
    }

    public function getMetadata($key = null)
    {
        return $this->innerStream->getMetadata($key);
    }
}

<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Psr\Http\Message\StreamInterface;

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

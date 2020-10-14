<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Image;

use TypeError;

/**
 * Encapsulates a streamable file resource
 */
class StreamableFile
{
    /** @var bool */
    private $isCacheHit;

    /** @var string */
    private $contentType;

    /** @var int */
    private $contentLength;

    /** @var resource */
    private $resource;

    /**
     * @param bool     $isCacheHit
     * @param string   $contentType
     * @param int      $contentLength
     * @param resource $resourceStream
     * @throws TypeError If $resourceStream is not a resource
     */
    public function __construct(bool $isCacheHit, string $contentType, int $contentLength, $resourceStream)
    {
        $this->isCacheHit    = $isCacheHit;
        $this->contentType   = $contentType;
        $this->contentLength = $contentLength;

        if (!is_resource($resourceStream)) {
            throw new TypeError("Expected resource, got " . gettype($resourceStream));
        }
        $this->resource = $resourceStream;
    }

    public function isCacheHit(): bool
    {
        return $this->isCacheHit;
    }

    /**
     * Outputs headers, streams content, and exits
     */
    public function stream(): void
    {
        header(sprintf('X-Cache-Status: %s', $this->isCacheHit ? 'HIT' : 'MISS'));
        header(sprintf('Content-type: %s', $this->contentType));
        header(sprintf('Content-length: %s', $this->contentLength));

        while (!feof($this->resource)) {
            // Read up to 1M bytes from the stream
            echo fread($this->resource, 1024 * 1024);
        }
        exit;
    }
}

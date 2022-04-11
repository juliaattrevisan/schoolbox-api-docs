<?php

/**
 * @copyright schoolbox 2022, Schoolbox Pty Ltd
 * @link https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

/**
 * Class InvalidPresignedUrlDurationExecption
 * @package Schoolbox\Resource\Storage\S3
 * Thrown when the presigned url duration is invalid (more than 7 days)
 */
class InvalidPresignedUrlDurationExecption extends \InvalidArgumentException
{
    /**
     * Returns HTTP status code
     *
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return 503;
    }

    /**
     * Returns header to not cache
     * @return string
     */
    public function getHeader(): string
    {
        return 'Cache-Control: no-store';
    }

    /**
     * sets message
     */
    public function __construct(string $message = 'Presigned url duration must be less than 7 days')
    {
        parent::__construct($message);
    }
}

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
}

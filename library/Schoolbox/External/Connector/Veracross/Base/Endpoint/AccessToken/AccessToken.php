<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\AccessToken;

/**
 * Veracross Access Token
 *
 * These have a 3600 second lifetime after which they must be regenerated
 */
class AccessToken
{
    /**
     * Constructor.
     *
     * @param int    $expiryTime
     * @param string $value
     */
    public function __construct(
        private int $expiryTime,
        private string $value
    ) {
    }


    /**
     * Decide if the current access token is about to expire.
     * @return bool
     */
    public function isExpired(): bool
    {
        return time() > $this->expiryTime;
    }


    /**
     * Return the value of this token
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }


    /**
     * Return a human-readable version of $expiryTime, for debugging
     *
     * @return string
     */
    public function getExpiryTime(): string
    {
        return  \Alaress_Date_Format::newFromTimestamp($this->expiryTime)->getDatabase();
    }
}

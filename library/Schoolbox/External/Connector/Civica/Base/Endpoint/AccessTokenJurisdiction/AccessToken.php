<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\AccessTokenJurisdiction;

/**
 * Veracross Access Token
 *
 * These have a 3600 second lifetime after which they must be regenerated
 *
 * Also store a copy of the payload Veracross sent to us, as a receipt
 * for diagnostics I guess
 */
class AccessToken
{
    /**
     * The actual value of the token, used in the header for
     * Authorization: Bearer
     *
     * @var string
     */
    private string $token;


    /**
     * Constructor.
     *
     * @param int    $expiryTime
     * @param array  $original
     */
    public function __construct(
        private int $expiryTime,
        private array $original
    ) {
        $this->token = $original['access_token'];
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
     * Return a human-readable version of $expiryTime, for debugging
     *
     * @return string
     */
    public function getExpiryTime(): string
    {
        return \Alaress_Date_Format::newFromTimestamp($this->expiryTime)->getDatabase();
    }


    /**
     * Return the value of this token
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }


    /**
     * Return the actual payload Veracross gave to us, for inspection
     *
     * @return array
     */
    public function getOriginalToken(): array
    {
        return $this->original;
    }
}

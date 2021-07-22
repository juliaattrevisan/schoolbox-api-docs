<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Remember;

/**
 * Full encapsulation of remember me logic.
 *
 * Contains the Cookie and Redis sub-parts of remember me, and exposes their
 * combined logic in a unified interface.
 */
class Remember
{
    /**
     * The cookie part of remember me: responsible for retrieving, remembering
     * and forgetting the remember me cookie.
     */
    private Cookie $rememberCookie;

    /**
     * The Redis part of remember me: responsible for retrieving, remembering
     * and forgetting the Redis hash.
     */
    private Redis $rememberRedis;

    /**
     * Constructor
     *
     * @param Cookie $rememberCookie
     * @param Redis  $rememberRedis
     */
    public function __construct(Cookie $rememberCookie, Redis $rememberRedis)
    {
        $this->rememberCookie = $rememberCookie;
        $this->rememberRedis  = $rememberRedis;
    }

    /**
     * Creates a remember me cookie and Redis key for this username.
     *
     * This username may then be retrieved by `retrieveUsername`, so long as
     * the cookie exists and the Redis key hasn't expired.
     *
     * @param string $username The username to remember
     * @param int    $expiry   The number of seconds to remember for
     */
    public function rememberUsername(string $username, int $expiry): void
    {
        $hash = $this->rememberRedis->rememberUsername($username, $expiry);
        $this->rememberCookie->rememberHash($hash, $expiry);
    }

    /**
     * Retrieve a username previously stored by `rememberUsername`.
     *
     * @return string|null The username, or null if none was stored.
     */
    public function retrieveUsername(): ?string
    {
        $hash = $this->rememberCookie->retrieveHash();
        if (isset($hash) && $hash) {
            return $this->rememberRedis->retrieveUsername($hash);
        }
        return null;
    }

    /**
     * Forgets a username previously stored by `rememberUsername`, removing the
     * username from Redis and clearing its associated cookie.
     */
    public function forgetUsername(): void
    {
        $hash = $this->rememberCookie->retrieveHash();
        if (isset($hash)) {
            $this->rememberRedis->forgetUsername($hash);
            $this->rememberCookie->forgetHash();
        }
    }
}

<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Remember;

/**
 * A mechanism for remembering a username which a user has logged in with.
 */
class Cookie
{
    public const DEFAULT_COOKIE_NAME = 'remember';

    /**
     * The name of the cookie to store the remember me hash in.
     */
    private string $cookieName;

    /**
     * The current cookies, as stored in `$_COOKIE` super-global.
     */
    private array $cookies;

    /**
     * The parameters to use when storing the cookie. Expected to be in the same
     * format as `session_get_cookie_params` returns, so should have these keys:
     * * path
     * * domain
     * * secure
     * * httponly
     */
    private array $cookieParams;

    /**
     * Constructor
     *
     * @param string $cookieName
     * @param array  $cookies
     * @param array  $cookieParams
     */
    public function __construct(string $cookieName, array $cookies, array $cookieParams)
    {
        $this->cookieName   = $cookieName;
        $this->cookies      = $cookies;
        $this->cookieParams = $cookieParams;
    }

    /**
     * Retrieve the hash from the current cookies.
     *
     * @return string|null The hash, or null if it doesn't exist.
     */
    public function retrieveHash(): ?string
    {
        return $this->cookies[$this->cookieName] ?? null;
    }

    /**
     * Forget the hash for the remembered user.
     *
     * @return bool
     */
    public function forgetHash(): bool
    {
        return $this->setCookie('', 1);
    }

    /**
     * Remember the hash for the remembered user.
     *
     * @param string $hash   The hash to remember
     * @param int    $expiry The number of seconds to remember for
     * @return bool
     */
    public function rememberHash(string $hash, int $expiry): bool
    {
        return $this->setCookie($hash, time() + $expiry);
    }

    /**
     * Helper method for setting cookies.
     *
     * @param string $hash      The hash to remember: empty string if forgetting
     * @param int    $expiresAt A Unix timestamp for the cookie's expiry
     * @return bool
     */
    private function setCookie(string $hash, int $expiresAt): bool
    {
        return setcookie(
            $this->cookieName,
            $hash,
            $expiresAt,
            $this->cookieParams['path'] ?? '',
            $this->cookieParams['domain'] ?? '',
            $this->cookieParams['secure'] ?? false,
            $this->cookieParams['httponly'] ?? false,
        );
    }
}

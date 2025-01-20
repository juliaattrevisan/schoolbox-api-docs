<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Client;

use Alaress_Date_Format;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Store OIDC client related values (state, nonce, PKCE token) in a cookie which
 * is not the standard session cookie, and is able to be accessed cross-domain.
 */
class CookieStore
{
    private const COOKIE_NAME = 'oidcClient';

    private const STATE_KEY     = 'state';
    private const PKCE_CODE_KEY = 'pkceCode';
    private const NONCE_KEY     = 'nonce';

    /**
     * Cookie expiry: for now, hardcode to 10 minutes.
     *
     * This matches the expiry of the auth code grant in
     * `\Schoolbox\Oidc\Server\Module::buildController`.
     */
    private const COOKIE_EXPIRY = 60 * 10;

    /**
     * Set the cookie which contains the state, PKCE code and nonce.
     *
     * @param string $state
     * @param string $pkceCode
     * @param string $nonce
     * @return void
     */
    public function set(string $state, string $pkceCode, string $nonce): void
    {
        setcookie(
            self::COOKIE_NAME,
            json_encode([
                self::STATE_KEY     => $state,
                self::PKCE_CODE_KEY => $pkceCode,
                self::NONCE_KEY     => $nonce,
            ]),
            [
                'expires'  => Alaress_Date_Format::now()->addSeconds(self::COOKIE_EXPIRY),
                'samesite' => 'None',
                'secure'   => true,
                'httponly' => true,
            ]
        );
    }

    /**
     * Retrieve the state value from the cookie.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function getState(ServerRequestInterface $request): ?string
    {
        return $request->getCookieParams()[self::STATE_KEY] ?? null;
    }

    /**
     * Retrieve the PKCE code value from the cookie.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function getPkceCode(ServerRequestInterface $request): ?string
    {
        return $request->getCookieParams()[self::PKCE_CODE_KEY] ?? null;
    }

    /**
     * Retrieve the nonce value from the cookie.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function getNonce(ServerRequestInterface $request): ?string
    {
        return $request->getCookieParams()[self::NONCE_KEY] ?? null;
    }

    /**
     * Clear the cookie completely.
     *
     * @return void
     */
    public function clear(): void
    {
        setcookie(self::COOKIE_NAME, '', 0);
    }
}

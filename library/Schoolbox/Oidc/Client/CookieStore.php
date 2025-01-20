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
 * Store OIDC client related values (state, nonce, PKCE token) in a set of
 * cookies which are not the standard session cookie.
 *
 * These cookies are designed to be sent as part of POST requests that an OIDC
 * provider sends back to Schoolbox, and thus have the `SameSite=None` setting.
 */
class CookieStore
{
    /**
     * Cookie names.
     */
    private const STATE_COOKIE     = 'oidcClient:state';
    private const PKCE_CODE_COOKIE = 'oidcClient:pkceCode';
    private const NONCE_COOKIE     = 'oidcClient:nonce';

    /**
     * Cookie expiry: for now, hardcode to 10 minutes.
     *
     * This matches the expiry of the auth code grant in
     * `\Schoolbox\Oidc\Server\Module::buildController`.
     */
    private const COOKIE_EXPIRY = 60 * 10;

    /**
     * Default cookie parameters:
     * * `SameSite=None` so that it's included in POST requests from other
     *   domains
     * * `Secure` so that it's only accessible in HTTPS requests
     * * `Http-Only` so that it's not accessible via JavaScript code
     */
    private const COOKIE_PARAMS = [
        'samesite' => 'None',
        'secure'   => true,
        'httponly' => true,
    ];

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
        $cookieParams = self::COOKIE_PARAMS + [
            'expires'  => Alaress_Date_Format::now()->addSeconds(self::COOKIE_EXPIRY)->getUnixTimeStamp(),
        ];
        setcookie(self::STATE_COOKIE, $state, $cookieParams);
        setcookie(self::PKCE_CODE_COOKIE, $pkceCode, $cookieParams);
        setcookie(self::NONCE_COOKIE, $nonce, $cookieParams);
    }

    /**
     * Retrieve the state value from the cookie.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function getState(ServerRequestInterface $request): ?string
    {
        return $request->getCookieParams()[self::STATE_COOKIE] ?? null;
    }

    /**
     * Retrieve the PKCE code value from the cookie.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function getPkceCode(ServerRequestInterface $request): ?string
    {
        return $request->getCookieParams()[self::PKCE_CODE_COOKIE] ?? null;
    }

    /**
     * Retrieve the nonce value from the cookie.
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function getNonce(ServerRequestInterface $request): ?string
    {
        return $request->getCookieParams()[self::NONCE_COOKIE] ?? null;
    }

    /**
     * Clear the cookies completely.
     *
     * @return void
     */
    public function clearAll(): void
    {
        setcookie(self::STATE_COOKIE, '', 0);
        setcookie(self::PKCE_CODE_COOKIE, '', 0);
        setcookie(self::NONCE_COOKIE, '', 0);
    }
}

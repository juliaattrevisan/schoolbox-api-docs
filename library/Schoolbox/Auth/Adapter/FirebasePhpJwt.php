<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Adapter;

use Firebase\JWT\JWT as JwtCodec;
use Okta\JwtVerifier;

/**
 * Override for Okta's Firebase JWT adapter.
 *
 * Supports all known algorithms, not just RS256.
 */
class FirebasePhpJwt extends JwtVerifier\Adaptors\FirebasePhpJwt
{
    /**
     * @param string $jwt
     * @param array $keys
     * @return JwtVerifier\Jwt
     */
    public function decode($jwt, $keys): JwtVerifier\Jwt
    {
        $decoded = (array)JwtCodec::decode($jwt, $keys, ['HS256']);
        return new JwtVerifier\Jwt($jwt, $decoded);
    }
}

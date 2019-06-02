<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Adapter;

use Okta\JwtVerifier;

/**
 * Override for Okta's Firebase JWT adapter.
 *
 * Overridden to support a hardcoded kid (key ID) field, as not all providers
 * provide this field as part of their JWT header.
 */
class FirebasePhpJwt extends JwtVerifier\Adaptors\FirebasePhpJwt
{
    /**
     * The ID of the key to use to decode the JWT.
     *
     * @var string|null
     */
    private $keyId;


    /**
     * Constructor
     *
     * @param string|null              $keyId
     * @param JwtVerifier\Request|null $request
     */
    public function __construct(?string $keyId = null, JwtVerifier\Request $request = null)
    {
        parent::__construct($request);
        $this->keyId = $keyId;
    }


    /**
     * @inheritDoc
     */
    public function getKeys($jku)
    {
        $keys = parent::getKeys($jku);
        if (isset($this->keyId, $keys[$this->keyId])) {
            return $keys[$this->keyId];
        }
        return $keys;
    }
}

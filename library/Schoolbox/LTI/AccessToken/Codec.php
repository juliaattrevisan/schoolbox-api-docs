<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Alaress\Codec\ICodec;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Codec for serializing/unserializing access tokens.
 */
class Codec implements ICodec
{
    /**
     * @param AccessToken $rawValue
     * @return string
     */
    public function encode($rawValue)
    {
        return serialize($rawValue);
    }

    /**
     * @param string $encodedValue
     * @return AccessToken
     */
    public function decode($encodedValue)
    {
        return unserialize($encodedValue);
    }
}

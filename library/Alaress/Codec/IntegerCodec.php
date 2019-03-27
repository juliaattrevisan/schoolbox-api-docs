<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec;

/**
 * A basic codec for encoding and decoding an integer into an obfuscated string
 * value.
 */
class IntegerCodec implements ICodec
{
    /**
     * @param int|null $rawValue
     * @return string|null
     */
    public function encode($rawValue): ?string
    {
        return isset($rawValue) ? base64_encode((string)$rawValue) : null;
    }


    /**
     * @param string|null $encodedValue
     * @return int|null
     */
    public function decode($encodedValue): ?int
    {
        return isset($encodedValue)
            ? filter_var(base64_decode($encodedValue), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
            : null;
    }
}

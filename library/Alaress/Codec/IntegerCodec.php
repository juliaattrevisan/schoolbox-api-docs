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
     * @inheritdoc
     *
     * @param int|null $rawValue
     * @return string|null
     * @throws \TypeError if $rawValue isn't an int or null
     */
    public function encode($rawValue): ?string
    {
        if (!isset($rawValue)) {
            return null;
        }
        if (!is_int($rawValue)) {
            throw new \TypeError('Only integer values may be encoded by this codec');
        }
        return base64_encode((string)$rawValue);
    }


    /**
     * @inheritdoc
     *
     * @param string|null $encodedValue
     * @return int|null
     * @throws \TypeError                if $encodedValue isn't a string or null
     * @throws \UnexpectedValueException if $encodedValue is a string, but
     *                                   decodes to a non-integer
     */
    public function decode($encodedValue): ?int
    {
        if (!isset($encodedValue)) {
            return null;
        }
        if (!is_string($encodedValue)) {
            throw new \TypeError('Only string values may be decoded by this codec');
        }
        $rawValue = filter_var(base64_decode($encodedValue), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (!isset($rawValue)) {
            throw new \UnexpectedValueException('Encoded value did not decode to an integer');
        }
        return $rawValue;
    }
}

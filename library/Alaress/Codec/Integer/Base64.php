<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec\Integer;

use Alaress\Codec\ICodec;

/**
 * A codec for encoding and decoding an integer into an obfuscated base64 string
 * value.
 */
class Base64 implements ICodec
{
    /**
     * @inheritdoc
     *
     * @param int $rawValue
     * @return string
     * @throws \TypeError if $rawValue isn't an int or null
     */
    public function encode($rawValue): string
    {
        if (!is_int($rawValue)) {
            throw new \TypeError('Only integer values may be encoded by this codec');
        }
        return base64_encode((string)$rawValue);
    }


    /**
     * @inheritdoc
     *
     * @param string $encodedValue
     * @return int
     * @throws \TypeError                if $encodedValue isn't a string or null
     * @throws \UnexpectedValueException if $encodedValue is a string, but
     *                                   decodes to a non-integer
     */
    public function decode($encodedValue): int
    {
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

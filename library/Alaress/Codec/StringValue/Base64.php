<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Codec\StringValue;

use Alaress\Codec\ICodec;
use TypeError;
use UnexpectedValueException;

/**
 * A codec for encoding and decoding a string into an obfuscated base64 string
 * value.
 *
 * The string is base64-encoded so that only a subset of characters may exist
 * within the encoded value, if it's to be put into a cache.
 */
class Base64 implements ICodec
{
    /**
     * @inheritdoc
     *
     * @param string $rawValue
     * @return string
     * @throws TypeError if $rawValue isn't a string
     */
    public function encode($rawValue): string
    {
        if (!is_string($rawValue)) {
            throw new TypeError('Only string values may be encoded by this codec');
        }
        return base64_encode($rawValue);
    }

    /**
     * @inheritdoc
     *
     * @param string $encodedValue
     * @return string
     * @throws TypeError                if $encodedValue isn't a string
     * @throws UnexpectedValueException if $encodedValue is a string, but
     *                                  does not decode to a string
     */
    public function decode($encodedValue): string
    {
        if (!is_string($encodedValue)) {
            throw new TypeError('Only string values may be decoded by this codec');
        }
        $rawValue = base64_decode($encodedValue);
        if (!is_string($rawValue)) {
            throw new UnexpectedValueException('Encoded value did not decode to an string');
        }
        return $rawValue;
    }
}

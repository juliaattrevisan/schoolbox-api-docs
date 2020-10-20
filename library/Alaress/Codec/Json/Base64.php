<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec\Json;

use Alaress\Codec\ICodec;
use TypeError;

/**
 * A codec for encoding and decoding any JSON-encodable value into an obfuscated
 * base64 string value.
 */
class Base64 implements ICodec
{
    /**
     * @inheritdoc
     *
     * @param mixed $rawValue
     * @return string
     */
    public function encode($rawValue): string
    {
        return base64_encode(json_encode($rawValue));
    }


    /**
     * @inheritdoc
     *
     * @param string $encodedValue
     * @return mixed
     * @throws TypeError if $encodedValue isn't a string or null
     */
    public function decode($encodedValue)
    {
        if (!is_string($encodedValue)) {
            throw new TypeError('Only string values may be decoded by this codec');
        }
        return json_decode(base64_decode($encodedValue), true);
    }
}

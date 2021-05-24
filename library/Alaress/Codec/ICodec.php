<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec;

/**
 * An interface for encoding and decoding.
 *
 * The codec process should be lossless, i.e. the following should be true,
 * so long as the initial input is deemed to be a legal value:
 * * `$codec->encode($codec->decode($encodedValue)) === $encodedValue`
 * * `$codec->decode($codec->encode($rawValue)) === $rawValue`
 *
 * Unfortunately PHP doesn't support generics, otherwise this would be defined
 * as:
 *
 * ```
 * interface ICodec<EncodedType, DecodedType>` {
 *     public function encode(DecodedType $rawValue):     EncodedType;
 *     public function decode(EncodedType $encodedValue): DecodedType;
 * }
 * ```
 */
interface ICodec
{
    /**
     * Encode this raw value into a format suitable for transmission.
     *
     * @param mixed $rawValue
     * @return mixed
     */
    public function encode($rawValue);


    /**
     * Decode this encoded value back into its raw value.
     *
     * @param mixed $encodedValue
     * @return mixed
     */
    public function decode($encodedValue);
}

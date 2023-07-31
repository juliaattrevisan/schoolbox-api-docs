<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

use Alaress\Codec\ICodec;
use Schoolbox\Search\Api\Cursor;

/**
 * Codec for the search API's cursor.
 */
class Codec implements ICodec
{
    /**
     * @param Cursor $rawValue
     * @return string
     */
    public function encode($rawValue): string
    {
        return base64_encode(
            json_encode(
                [
                    $rawValue->relevance,
                    $rawValue->title,
                    $rawValue->type,
                    $rawValue->id,
                ]
            )
        );
    }

    /**
     * @param string $encodedValue
     * @return Cursor|null
     */
    public function decode($encodedValue): ?Cursor
    {
        $value = json_decode(base64_decode($encodedValue));
        if (!is_array($value) && !isset($value[0], $value[1], $value[2], $value[3])) {
            return null;
        }
        return new Cursor($value[0], $value[1], $value[2], $value[3]);
    }
}

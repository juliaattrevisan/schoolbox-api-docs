<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

use Alaress\Codec\ICodec;

/**
 * Codec for the search API's cursor.
 *
 * - encoded format: a base64-encoded string
 * - decoded format: a `DataObject` instance
 */
class Codec implements ICodec
{
    /**
     * @param DataObject $rawValue
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
     * @param string|null $encodedValue
     * @return DataObject|null
     */
    public function decode($encodedValue): ?DataObject
    {
        if (!isset($encodedValue)) {
            return null;
        }
        $value = json_decode(base64_decode($encodedValue));
        if (!is_array($value) && !isset($value[0], $value[1], $value[2], $value[3])) {
            return null;
        }
        return new DataObject($value[0], $value[1], $value[2], $value[3]);
    }
}

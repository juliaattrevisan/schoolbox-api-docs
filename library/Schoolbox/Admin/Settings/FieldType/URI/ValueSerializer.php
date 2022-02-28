<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\URI;

use Schoolbox\Admin\Settings\IFieldValueSerializer;

class ValueSerializer implements IFieldValueSerializer
{

    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        if ($value === '') {
            return '';
        }
        $value = trim($value);
        if (null === parse_url($value, PHP_URL_SCHEME)) {
            throw new \UnexpectedValueException('"oAuth Identity Provider URL" is not a valid URL.');
        }
        return $value;
    }
}

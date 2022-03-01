<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\URI;

use Schoolbox\Admin\Settings\IFieldValueSerializer;
use Laminas\Uri\Uri;

class ValueSerializer implements IFieldValueSerializer
{

    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $url = new Uri($value);
        if (!$url->isValid()) {
            throw new \UnexpectedValueException("Invalid URL supplied");
        }
        return $value;
    }
}

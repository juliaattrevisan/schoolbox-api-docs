<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\URI;

use Schoolbox\Admin\Settings\IFieldValueSerializer;
use Schoolbox\Url\Uri;

class ValueSerializer implements IFieldValueSerializer
{
    /**
     * @inheritdoc
     */
    public function serializeValue($value, $config): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            $url = new Uri($value);
        } catch (\InvalidArgumentException $e) {
            throw new \UnexpectedValueException("Invalid URL supplied");
        }
        return $value;
    }
}

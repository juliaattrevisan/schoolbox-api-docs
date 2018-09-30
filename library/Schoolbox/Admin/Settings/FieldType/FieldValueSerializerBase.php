<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType;

use Schoolbox\Admin\Settings\IFieldValueSerializer;

class FieldValueSerializerBase implements IFieldValueSerializer
{

    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        // Default implementation
        return (string)$value;
    }
}

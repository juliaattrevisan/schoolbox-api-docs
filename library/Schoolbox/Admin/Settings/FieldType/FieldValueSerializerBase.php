<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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

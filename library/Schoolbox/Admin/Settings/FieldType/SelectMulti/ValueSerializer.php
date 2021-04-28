<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectMulti;

use Schoolbox\Admin\Settings\IFieldValueSerializer;

class ValueSerializer implements IFieldValueSerializer
{

    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        return implode(Renderer::VALUE_DELIMITER, $value);
    }
}

<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Range;

use Schoolbox\Admin\Settings\IFieldValueSerializer;

class ValueSerializer implements IFieldValueSerializer
{
    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Duration;

use Schoolbox\Admin\Settings\FieldType\Number\IntValueSerializer;

class ValueSerializer extends IntValueSerializer
{
    /**
     * @inheritdoc
     */
    public function serializeValue($value, $config): string
    {
        $value = trim((string)$value);

        // 0 represents disabled, only when the field is optional
        if (!$config->getRequired() && ('' === $value || '0' === $value)) {
            return '0';
        }

        // otherwise, ensure the value is within the expected range
        return parent::serializeValue($value, $config);
    }

    /**
     * Converts a string representation of options into an array form
     * The options for a duration are a JSON object with the following keys:
     * - min: int - The minimum value
     * - max: int - The maximum value
     * - timeUnit: 'year'|'week'|'day'|'hour'|'minute' - The time unit the duration is stored in
     *
     * @param string $optionsString
     * @return ?array{min:?int,max:?int,timeUnit:?string}
     */
    public function buildOptions(string $optionsString): ?array
    {
        return json_decode($optionsString, true);
    }
}

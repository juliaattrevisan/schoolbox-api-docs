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
        // Attempt to parse the value, if it can't be parsed then handle as if it were unset
        $value = trim((string)$value);
        if (!is_numeric($value)) {
            $value = '';
        }

        // '0' is equivalent to a blank value, as a duration of 0 is the same as no duration at all
        if ('0' === $value) {
            $value = '';
        }

        // Allow the field to be optional if it is not required (otherwise the parent serializer enforces the min/max which isn't the same as optional)
        if (!$config->getRequired() && '' === $value) {
            return '';
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

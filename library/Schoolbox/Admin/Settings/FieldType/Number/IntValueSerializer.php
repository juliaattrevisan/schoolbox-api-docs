<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Number;

use Schoolbox\Admin\Settings\IFieldValueSerializer;

class IntValueSerializer implements IFieldValueSerializer
{
    /**
     * values are stored in the db as string (so we are casting things as string)
     * @inheritdoc
     */
    public function serializeValue($value, $config): string
    {
        $options = $this->buildOptions($config->getOptions() ?? '');

        // If invalid, return the correct value based on the options
        if (!$this->isValueValid($value, $options)) {
            if (isset($options['min']) && $value < $options['min']) {
                return (string)$options['min'];
            }
            if (isset($options['max']) && $value > $options['max']) {
                return (string)$options['max'];
            }
        }

        return $value;
    }

    /**
     * Converts a string representation of options into an array form
     * The options for a range are a JSON object with the following keys:
     * - min: int - The minimum value
     * - max: int - The maximum value
     *
     * @param string $optionsString
     * @return ?array{min:int,max:int}
     */
    public function buildOptions(string $optionsString): ?array
    {
        return json_decode($optionsString, true);
    }

    /**
     * Returns whether the current value is valid because it matches the range options
     *
     * @param string|null $value
     * @param ?array{min:int,max:int} $options
     * @return bool
     */
    protected function isValueValid(?string $value, ?array $options): bool
    {
        // no options, just exit
        if (null === $options) {
            return true;
        }

        // Can the value be cast to a int?
        if (!is_numeric($value)) {
            return false;
        }

        $value = (int) $value;

        // Is the value within the range?
        if (isset($options['min']) && $value < $options['min']) {
            return false;
        }
        if (isset($options['max']) && $value > $options['max']) {
            return false;
        }

        return true;
    }
}

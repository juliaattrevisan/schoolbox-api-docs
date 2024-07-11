<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Range;

use Schoolbox\Admin\Settings\IFieldValueSerializer;

class ValueSerializer implements IFieldValueSerializer
{
    /**
     * @inheritdoc
     */
    public function serializeValue($value, $config): string
    {
        $options = $this->buildOptions($config->getOptions());

        // If invalid, return the maximum value instead
        if (!$this->isValueValid($value, $options)) {
            return (string) $options['max'];
        }

        return $value;
    }

    /**
     * Converts a string representation of options into an array form
     * The options for a range are a JSON object with the following keys:
     * - min: float - The minimum value
     * - max: float - The maximum value
     * - step: float - The step value
     *
     * @param string $optionsString
     * @return array{min:float,max:float,step:float}
     */
    public function buildOptions(string $optionsString): array
    {
        return json_decode($optionsString, true);
    }

    /**
     * Returns whether the current value is valid because it matches the range options
     *
     * @param string|null $value
     * @param array{min:float,step:float,max:float} $options
     * @return bool
     */
    protected function isValueValid(?string $value, array $options): bool
    {
        // Can the value be cast to a float?
        if (!is_numeric($value)) {
            return false;
        }

        $value = (float) $value;

        // Is the value within the range?
        if ($value < $options['min'] || $value > $options['max']) {
            return false;
        }

        // Does the value match the step?
        if (($value - $options['min']) % $options['step'] !== 0) {
            return false;
        }

        return true;
    }
}

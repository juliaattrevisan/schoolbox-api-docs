<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Range;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
{
    /**
     * @inheritdoc
     */
    protected function getTemplate(): string
    {
        return 'range.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $data = parent::getData($config);
        $options = $this->buildOptions($config->getOptions() ?? '');

        $data["min"] = $options["min"];
        $data["max"] = $options["max"];
        $data["step"] = $options["step"];

        $isValidValue = $this->isValueValid($data['value'], $options);

        if (!$isValidValue) {
            $data['value'] = $this->castInvalidValue($options);
        }

        return $data;
    }

    protected function renderEmptyOption(Config $config, bool $isValidValue): bool
    {
        return !$config->getRequired() || !$isValidValue;
    }

    /**
     * Converts an invalid value to a valid one suitable for passing to front-end
     * In this scenario, the value is set to the minimum value
     *
     * @param array{min:float,step:float,max:float} $options
     * @return float
     */
    protected function castInvalidValue(array $options)
    {
        return $options['min'];
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
    protected function buildOptions(string $optionsString): array
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

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_RANGE;
    }
}

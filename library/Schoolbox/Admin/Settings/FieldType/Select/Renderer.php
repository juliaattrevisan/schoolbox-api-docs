<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Select;

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
        return 'select.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $data = \array_merge(parent::getData($config), ['options' => $this->buildOptions($config->getOptions())]);

        $isValidValue = $this->isValueValid($data['value'], $data['options']);

        if (!$isValidValue) {
            $data['value'] = $this->castInvalidValue($data['value'], $data['options']);
        }

        // We need to render an empty option if:
        // * config is optional, or
        // * config value is invalid
        $data['renderEmptyOption'] = $this->renderEmptyOption($config, $isValidValue);

        $data['multi'] = false;
        return $data;
    }

    protected function renderEmptyOption(Config $config, bool $isValidValue): bool
    {
        return !$config->getRequired() || !$isValidValue;
    }


    /**
     * Converts an invalid value to a valid one suitable for passing to front-end
     *
     * @param string|null $value
     * @param string[] $options
     * @return mixed
     */
    protected function castInvalidValue(?string $value, array $options)
    {
        return null;
    }


    /**
     * Converts a string representation of options into an array form
     *
     * @param string $optionsString
     * @return array
     */
    protected function buildOptions(string $optionsString): array
    {
        $options = [];
        foreach (\explode(':', $optionsString) as $value) {
            $options[$value] = $value;
        }
        return $options;
    }


    /**
     * Returns whether the current value is valid because it matches one of the available options
     *
     * @param string|null $value
     * @param string[]    $options
     * @return bool
     */
    protected function isValueValid(?string $value, array $options): bool
    {
        foreach ($options as $option) {
            if ($option === $value) {
                return true;
            }
        }
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT;
    }
}

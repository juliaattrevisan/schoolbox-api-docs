<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Select;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
{
    public const EMPTY_OPTION_LABEL = '-- select an option --';

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
        $options = $this->buildOptions($config->getOptions());

        return \array_merge(parent::getData($config), [
            'options'          => $options,
            'emptyOptionLabel' => self::EMPTY_OPTION_LABEL,
        ]);
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
     * Returns the config's value, applying any relevant transformations
     *
     * @param Config $config
     * @return null|string
     */
    protected function getTransformedValue(Config $config): ?string
    {
        // Check that the current configured value is valid
        $configuredValue = $config->getValue();

        // Not set?
        if (null === $configuredValue) {
            // That's fine, return unchanged.
            return null;
        }

        foreach (\explode(':', $config->getOptions()) as $value) {
            if ($value === $configuredValue) {
                // Value is valid, return it unchanged.
                return $configuredValue;
            }
        }
        // Current value is not valid. Return null.
        return null;
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT;
    }
}

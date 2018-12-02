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

        if(!$isValidValue){
            $data['value'] = null;
        }

        // We need to render an empty option if:
        // * config is optional, or
        // * config value is invalid
        $data['renderEmptyOption'] = !$config->getRequired() || !$isValidValue;

        return $data;
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


    protected function isValueValid(?string $value, array $options): bool
    {
        foreach ($options as $key => $option) {
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

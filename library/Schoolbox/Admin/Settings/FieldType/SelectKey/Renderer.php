<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectKey;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Admin\Settings\FieldType\Select;

class Renderer extends Select\Renderer
{

    /**
     * Converts a string representation of options into an array form
     *
     * @param string $optionsString
     * @return array
     */
    protected function buildOptions(string $optionsString): array
    {
        $options = [];
        foreach (\explode(':', $optionsString) as $keyAndValue) {
            [$key, $val] = \explode(';', $keyAndValue);
            $options[$key] = $val;
        }
        return $options;
    }

    protected function isValueValid(?string $value, array $options): bool
    {
        foreach ($options as $key => $option) {
            if ((string)$key === $value) {
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
        return ConfigMapper::FIELD_SELECT_KEY;
    }
}

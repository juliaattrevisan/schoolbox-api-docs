<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectKey;

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

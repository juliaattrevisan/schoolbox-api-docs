<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectMulti;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

use function explode;
use function implode;
use function in_array;

class Renderer extends \Schoolbox\Admin\Settings\FieldType\Select\Renderer
{
    // Character we use to delimit multiple values in value field
    public const VALUE_DELIMITER = ',';

    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $data          = parent::getData($config);
        $data['multi'] = true;
        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT_MULTI;
    }

    /**
     * Valid is valid if all component values match an available option
     *
     * @inheritdoc
     */
    protected function isValueValid(?string $value, array $options): bool
    {
        foreach (explode(self::VALUE_DELIMITER, $value ?? '') as $v) {
            if (!in_array($v, $options, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converts an invalid value to a valid one suitable for passing to front-end
     *
     * @param string|null $value
     * @param string[]    $options
     * @return mixed
     */
    protected function castInvalidValue(?string $value, array $options)
    {
        $validValues = [];
        foreach (explode(self::VALUE_DELIMITER, $value ?? '') as $v) {
            if (in_array($v, $options, true)) {
                $validValues[] = $v;
            }
        }
        return implode(self::VALUE_DELIMITER, $validValues);
    }

    /**
     * Never render empty option, as user can just deselect all options
     *
     * @param Config $config
     * @param bool   $isValidValue
     * @return bool
     */
    protected function renderEmptyOption(Config $config, bool $isValidValue): bool
    {
        return false;
    }
}

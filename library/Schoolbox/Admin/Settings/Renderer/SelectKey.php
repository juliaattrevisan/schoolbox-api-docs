<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class SelectKey extends Select
{

    /**
     * @inheritdoc
     */
    protected function getData(Config $config): array
    {
        $options = [];
        foreach (\explode(':', $config->getOptions()) as $keyAndValue) {
            [$key, $val] = \explode(';', $keyAndValue);
            $options[$key] = $val;
        }

        return \array_merge(parent::getData($config), [
            'options' => $options,
        ]);
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT_KEY;
    }
}

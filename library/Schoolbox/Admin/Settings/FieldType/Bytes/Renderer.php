<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Bytes;

use Alaress_Byte_Format;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Admin\Settings\FieldType\Input;

class Renderer extends Input\Renderer
{

    /**
     * @inheritdoc
     */
    protected function getTransformedValue(Config $config): string
    {
        return Alaress_Byte_Format::bytesToString($config->getValue());
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_BYTES;
    }
}

<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Alaress_Byte_Format;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Bytes extends Input
{

    /**
     * @inheritdoc
     */
    protected function getValue(Config $config): string
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

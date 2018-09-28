<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Boolean;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Admin\Settings\FieldType\Checkbox;

class Renderer extends Checkbox\Renderer
{

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_BOOLEAN;
    }
}

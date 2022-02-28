<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\URI;

use Schoolbox\Admin\Settings\FieldType\Input;
use Schoolbox\Config\ConfigMapper;

class Renderer extends Input\Renderer
{
    protected function getTemplate(): string
    {
        return 'input.twig';
    }

    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_URI;
    }
}

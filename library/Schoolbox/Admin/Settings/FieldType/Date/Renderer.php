<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Date;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Admin\Settings\FieldType\Input;

class Renderer extends Input\Renderer
{
    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT_DATE;
    }

    /**
     * Returns the template fragment suitable for rendering this renderer's config field type
     *
     * @return string
     */
    protected function getTemplate(): string
    {
        return 'date.twig';
    }
}

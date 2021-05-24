<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Input;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
{

    /**
     * Returns the id of the field type this renderer relates to. Used to ensure we don't
     * attempt to render a config of an incompatible type
     *
     * @return int
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_INPUT;
    }


    /**
     * Returns the template fragment suitable for rendering this renderer's config field type
     *
     * @return string
     */
    protected function getTemplate(): string
    {
        return 'input.twig';
    }
}

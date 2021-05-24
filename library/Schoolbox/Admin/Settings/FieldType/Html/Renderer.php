<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Html;

use Schoolbox\Admin\Settings\FieldType\AbstractRenderer;
use Schoolbox\Config\ConfigMapper;

class Renderer extends AbstractRenderer
{

    /**
     * @inheritdoc
     */
    protected function getTemplate(): string
    {
        return 'html.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_HTML;
    }
}

<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\ConfigMapper;

class Html extends Base
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

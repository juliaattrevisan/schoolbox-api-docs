<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\ConfigMapper;

class Password extends Input
{

    protected function getTemplate(): string
    {
        return 'password.twig';
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_PASSWORD;
    }
}

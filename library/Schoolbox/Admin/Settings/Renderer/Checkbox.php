<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

class Checkbox extends Base
{

    /**
     * @inheritdoc
     */
    public function getTemplate(): string
    {
        return 'checkbox.twig';
    }
}

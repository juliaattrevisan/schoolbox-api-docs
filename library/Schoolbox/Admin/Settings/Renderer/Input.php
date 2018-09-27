<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;

class Input extends Base
{

    /**
     * Returns the template fragment suitable for rendering this renderer's config field type
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'input.twig';
    }


    /**
     * Returns the config's value, applying any relevant transformations
     *
     * @param Config $config
     * @return string
     */
    protected function getValue(Config $config): string
    {
        return $config->getValue();
    }
}

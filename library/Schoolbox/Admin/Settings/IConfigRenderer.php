<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Schoolbox\Admin\Settings\Exceptions\FieldTypeMismatchException;
use Schoolbox\Config\Config;

/**
 * Instances of this class are able to build a matching template and data pair
 * that can be rendered in the view layer.
 */
interface IConfigRenderer
{

    /**
     * Returns the template and data to render into the template with keys 'template' and 'data'
     *
     * @param Config $config
     * @return mixed
     * @throws FieldTypeMismatchException If config and this renderer are for different field types
     */
    public function getTemplateAndData(Config $config);
}

<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Schoolbox\Config\Config;

/**
 * Instances of this interface can convert a POSTed value to a form suitable for persistence
 */
interface IFieldValueSerializer
{

    /**
     * @param mixed $value
     * @param Config $config
     * @return string
     */
    public function serializeValue($value, Config $config): string;
}

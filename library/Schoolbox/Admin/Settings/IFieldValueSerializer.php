<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

/**
 * Instances of this interface can convert a POSTed value to a form suitable for serialisation.
 */
interface IFieldValueSerializer
{
    public function serializeValue($value): string;
}

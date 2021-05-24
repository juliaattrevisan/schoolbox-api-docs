<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json;

use Alaress\Json\Decorator\IDecorator;

/**
 * Interface that extends the core JsonSerializable interface so that decoration strategies can be specified
 *
 * @package Alaress\Json
 */
interface Serializable extends \JsonSerializable
{
    /**
     * @return array
     */
    public function getDefaultJsonMap(): array;


    /**
     * @param IDecorator ...$decorators
     * @return mixed
     */
    public function jsonSerialize(IDecorator ...$decorators);
}

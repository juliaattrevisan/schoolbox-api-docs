<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json;

use Alaress\Json\Decorator\IDecorator;


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
    public function jsonSerialize(IDecorator... $decorators);
}
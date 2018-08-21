<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json\Decorator;

interface IDecorator
{
    /**
     * Decorate thew given input according
     *
     * @param mixed  $array
     * @param object $model
     * @return array
     */
    public function decorate(array $array, $model): array;
}
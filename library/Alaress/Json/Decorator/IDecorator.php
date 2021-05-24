<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json\Decorator;

/**
 * Interface for defining a JSON decorator.
 *
 * @package Alaress\Json\Decorator
 */
interface IDecorator
{
    /**
     * Decorate the given JSON payload according to the model given.
     *
     * @param array  $json   - the JSON payload, as it exists so far, to be decorated
     * @param object $model  - the object with which to decorate the given JSON payload
     * @return array         - the mutated payload
     */
    public function decorate(array $json, $model): array;
}

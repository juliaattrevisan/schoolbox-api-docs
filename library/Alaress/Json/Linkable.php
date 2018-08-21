<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json;

/**
 * Interface that MUST be implemented by a model for \Alaress\Json\Decorator\LinkDecorator to have any effect.
 *
 * @package Alaress\Json
 */
interface Linkable
{

    /**
     * Return an array in the following syntax:
     * [
     *     KEY => [
     *         ROUTE_NAME, [
     *             ARG_NAME => $this->value,
     *             ...
     *         ]
     *     ],
     *     ...
     * ]
     *
     * eg, [
     *     'self' => [
     *         'getEntity' => [ 'entityId' => $this->getId() ]
     *     ],
     * ]
     *
     * @return array
     */
    public function getJsonApiLinks(): array;

}
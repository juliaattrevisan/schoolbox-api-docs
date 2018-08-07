<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Di;

interface IDiConfigurable
{
    /**
     * Returns the $di->params[static:class] for this class
     *
     * @param \Aura\Di\ContainerInterface $di
     * @return array
     */
    public static function getParams(\Aura\Di\ContainerInterface $di): ?array;

    /**
     * Returns the $di->setters[static:class] for this class
     *
     * @param \Aura\Di\ContainerInterface $di
     * @return array
     */
    public static function getSetters(\Aura\Di\ContainerInterface $di): ?array;

}
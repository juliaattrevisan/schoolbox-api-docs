<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Di;

/**
 * Interface for allowing classes to statically declare their DI dependencies.
 * Designed to reduce the coupling of everything being declared in a central location like Bootstrap, and allowing
 * classes to have more control over how they're constructed. These methods are designed to be used by the
 * Configurator class.
 *
 * Interface IDiConfigurable
 * @package Schoolbox\Di
 */
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

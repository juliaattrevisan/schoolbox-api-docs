<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Di;

use Psr\Container\ContainerInterface;

/**
 * Interface for allowing classes to statically declare their dependencies.
 *
 * Designed to reduce the coupling of everything being declared in a central
 * location like Bootstrap, and allowing classes to have more control over how
 * they're constructed.
 *
 * These methods are designed to be used by `\Schoolbox\Di\Configurator`.
 *
 * Interface IDiConfigurable
 * @package Schoolbox\Di
 */
interface IDiConfigurable
{
    /**
     * Returns the constructor parameters for this class.
     *
     * @param ContainerInterface $di
     * @return array
     */
    public static function getParams(ContainerInterface $di): ?array;

    /**
     * Returns the required setters for this class.
     *
     * @param ContainerInterface $di
     * @return array
     */
    public static function getSetters(ContainerInterface $di): ?array;
}

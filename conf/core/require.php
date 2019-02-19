<?php
/**
 * File used to require essential autoload definitions.
 * Intentionally lowercase filename so it can't be found by regular autoloaders.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Register a special autoloader for loading a kernel
 *
 * @param string $className
 * @return bool
 * @throws InvalidArgumentException If we attempt to load a kernel that this method doesn't recognise
 */
function kernel_load(string $className)
{
    // kernel definitions
    $kernelDaemon   = \Schoolbox\Application\Daemon\Kernel::class;
    $kernelWebFull  = \Schoolbox\Application\WebRequest\Kernel::class;
    $kernelWebBasic = \Schoolbox\Application\BasicRequest\Kernel::class;

    // abstraction dependencies
    if (!\in_array($className, [$kernelWebFull, $kernelWebBasic, $kernelDaemon], true)) {
        // Not loading a kernel, we're done here.
        return false;
    }
    require_once __DIR__ . '/../../library/Alaress/Application/Kernel.php';

    // Are we loading a web kernel of some sort?
    if (\in_array($className, [$kernelWebFull, $kernelWebBasic], true)) {
        require_once __DIR__ . '/../../library/Schoolbox/Application/WebKernelAbstract.php';
    }

    // Load concrete kernel file
    switch ($className) {
        case $kernelDaemon:
            require_once __DIR__ . '/../../library/Schoolbox/Application/Daemon/Kernel.php';
            break;
        case $kernelWebBasic:
            require_once __DIR__ . '/../../library/Schoolbox/Application/BasicRequest/Kernel.php';
            break;
        case $kernelWebFull:
            require_once __DIR__ . '/../../library/Schoolbox/Application/WebRequest/Kernel.php';
            break;
        default:
            throw new InvalidArgumentException("Unrecognised kernel class: $className");
    }

    // this only gets used once, so we're finished now
    spl_autoload_unregister(__METHOD__);
    return true;
}

spl_autoload_register('kernel_load');

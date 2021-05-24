<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Di;

use Aura\Di\Exception\ReflectionFailure;

class Configurator
{
    /** @var \Aura\Di\Container */
    private $di;


    /**
     * @param \Aura\Di\Container $di
     */
    public function __construct(\Aura\Di\Container $di)
    {
        $this->di = $di;
    }


    /**
     * @param string $classname
     * @throws ReflectionFailure
     */
    public function configureClass(string $classname): void
    {
        try {
            if (!(new \ReflectionClass($classname))->implementsInterface(IDiConfigurable::class)) {
                throw new ReflectionFailure("$classname does not implement " . IDiConfigurable::class);
            }
        } catch (\ReflectionException $e) {
            throw new ReflectionFailure($e->getMessage(), $e->getCode(), $e);
        }

        /** @var IDiConfigurable $classname */
        $diParams = $classname::getParams($this->di);
        $diSetters = $classname::getSetters($this->di);

        $this->di->params[$classname]  = $diParams;
        $this->di->setters[$classname] = $diSetters;
    }


    /**
     * @param string $classname
     * @return object
     * @throws \Aura\Di\Exception
     */
    public function newInstance(string $classname)
    {
        return $this->di->newInstance($classname);
    }
}

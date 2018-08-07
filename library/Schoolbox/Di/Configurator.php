<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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

        $this->di->params[$classname]  = $classname::getParams($this->di);
        $this->di->setters[$classname] = $classname::getSetters($this->di);
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

<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Di;

use Aura\Di\Exception\ReflectionFailure;
use ReflectionClass;

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


//    /**
//     * @param string $classname
//     * @throws ReflectionFailure
//     */
//    public function configureClass(string $classname): void
//    {
//        try {
//            if (!(new \ReflectionClass($classname))->implementsInterface(IDiConfigurable::class)) {
//                throw new ReflectionFailure("$classname does not implement " . IDiConfigurable::class);
//            }
//        } catch (\ReflectionException $e) {
//            throw new ReflectionFailure($e->getMessage(), $e->getCode(), $e);
//        }
//
//        /** @var IDiConfigurable $classname */
//        $diParams = $classname::getParams($this->di);
//        $diSetters = $classname::getSetters($this->di);
//
//        $this->di->params[$classname]  = $diParams;
//        $this->di->setters[$classname] = $diSetters;
//    }


    /**
     * @template T of IDiConfigurable
     * @param class-string<T> $classname
     * @return T
     */
    public function newInstance(string $classname)
    {
        // First must check if the class implements the required interface
        if (!is_a($classname, IDiConfigurable::class, true)) {
            throw new ReflectionFailure("$classname does not implement " . IDiConfigurable::class);
        }

        // Don't use Aura DI: create the thing directly instead
        $reflection      = new ReflectionClass($classname);
        $constructor     = $reflection->getConstructor();
        $constructorArgs = [];
        if ($constructor) {
            // Figure out constructor arguments
            $constructorParams = $classname::getParams($this->di);
            foreach ($constructor->getParameters() as $parameter) {
                // Get the configured value for this argument.
                // If it's not there, use the default value:
                // if there's no default value, do not add more arguments.
                $paramName = $parameter->getName();
                if (array_key_exists($paramName, $constructorParams)) {
                    $constructorArgs[] = $constructorParams[$paramName];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $constructorArgs[] = $parameter->getDefaultValue();
                } else {
                    break;
                }
            }
        }

        // Build the object and call whatever its setters are.
        $instance = $reflection->newInstance($constructorArgs);
        foreach ($classname::getSetters($this->di) as $setter => $value) {
            if ($reflection->hasMethod($setter)) {
                $instance->$setter($value);
            }
        }
        return $instance;
//        return $this->di->newInstance($classname);
    }
}

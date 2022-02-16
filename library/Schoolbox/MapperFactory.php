<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Aura\Di\Container;
use Aura\Di\Injection\Lazy;
use InvalidArgumentException;

class MapperFactory
{
    /** @var Container */
    protected $injector;

    /**
     * @param Container $injector
     */
    public function __construct(Container $injector)
    {
        $this->injector = $injector;
    }

    /**
     * Get instance of specified mapper
     *
     * Use this when you need a Mapper - as opposed to getLazy()
     *
     * @template T
     * @param class-string<T> $mapperName
     * @return T
     * @throws InvalidArgumentException If mapper class not found
     */
    public function get($mapperName)
    {
        if (!class_exists($mapperName)) {
            throw new InvalidArgumentException('Mapper class not found: ' . $mapperName);
        }
        // No longer injectable, because the container is locked as soon as
        // sonething is extracted from it
        if (is_callable([ $mapperName, 'getInstance' ])) {
            return $mapperName::getInstance();
        }
        return new $mapperName();
    }

    /**
     * Gets Lazy reference to the specified mapper
     *
     * Use this when registering a service on the DI.
     * Only the DI knows how to transform a lazy ref to a Mapper into a Mapper instance.
     *
     * @template T
     * @param class-string<T> $mapperName
     * @throws InvalidArgumentException If mapper class not found
     * @return Lazy
     */
    public function getLazy($mapperName)
    {
        if (!class_exists($mapperName)) {
            throw new InvalidArgumentException('Mapper class not found: ' . $mapperName);
        }
        return $this->injector->lazy(function () use ($mapperName) {
            // utilise getInstance if possible
            if (is_callable([ $mapperName, 'getInstance' ])) {
                return $mapperName::getInstance();
            }

            return new $mapperName();
        });
    }
}

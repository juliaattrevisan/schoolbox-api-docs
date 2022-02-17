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

        // Does this have a getInstance? Use it if so
        if (is_callable([ $mapperName, 'getInstance' ])) {
            $mapper = $mapperName::getInstance();
        } else {
            $mapper = new $mapperName();
        }

        // Is this a genuine mapper? Set its session if so.
        // TODO decouple the dependency between mappers and session
        if ($mapper instanceof Mapper) {
            $session = $this->injector->get('session');
            if (!$session->isStarted()) {
                $session->start();
            }
            $mapper->setSessionData($_SESSION ?? []);
        }

        return $mapper;
    }

    /**
     * Gets Lazy reference to the specified mapper
     *
     * Use this when registering a service on the DI: only the DI container
     * knows how to transform a lazy ref to a Mapper into a Mapper instance.
     *
     * @template T
     * @param class-string<T> $mapperName
     * @return Lazy
     * @throws InvalidArgumentException If mapper class not found
     */
    public function getLazy($mapperName)
    {
        if (!class_exists($mapperName)) {
            throw new InvalidArgumentException('Mapper class not found: ' . $mapperName);
        }
        return $this->injector->lazy(
            function () use ($mapperName) {
                return $this->get($mapperName);
            }
        );
    }
}

<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Admin\API;

use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\External;
use Schoolbox\Import\ImporterFactory;

/**
 * Class Module
 *
 * @package Schoolbox\Admin\API
 */
class Module extends BaseModule
{
    protected $basename = 'adminApi';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // no explicit auth; all done via tokens

        $this->register('POST', '/csv',     'postUploadCsv');
        $this->register('POST', '/process', 'postProcess');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        $injector->params[Controller::class] = [
            'storageMapper'   => $injector->get('mapperFactory')->getLazy(\Schoolbox\Resource\Storage\Mapper::class),
            'importerFactory' => $injector->lazyNew(ImporterFactory::class),
            'baseMapper' => $this->getMapperFactory()->getLazy(External\Profile\Mapper::class),
            'parentMapper' => $this->getMapperFactory()->getLazy(External\Profile\Parent\Mapper::class),
            'studentMapper' => $this->getMapperFactory()->getLazy(External\Profile\Student\Mapper::class),
            'staffMapper' => $this->getMapperFactory()->getLazy(External\Profile\Staff\Mapper::class),
            'relationshipMapper' => $this->getMapperFactory()->getLazy(External\Profile\Relationship\Mapper::class),
        ];
    }
}

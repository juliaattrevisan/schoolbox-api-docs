<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\API;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\External;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

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

        $this->register('POST', '/csv', 'postUploadCsv');
        $this->register('POST', '/process', 'postProcess');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(StorageMapper::class),
            new ImporterFactory(),
            $this->getMapperFactory()->get(External\Profile\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Parent\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Student\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Staff\Mapper::class),
            $this->getMapperFactory()->get(External\Profile\Relationship\Mapper::class),
            $this->injector->get('filesystem.storageProxy'),
        );
    }
}

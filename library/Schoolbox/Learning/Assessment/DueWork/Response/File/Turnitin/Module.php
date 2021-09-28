<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

/**
 * Turnitin Webhooks route through this controller
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'turnitinWebhook';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Anyone can post to the endpoint
         */
        $postPerms = null;

        $this->register('GET', '/upload-complete', 'getUploadComplete', $postPerms);
        $this->register('GET', '/report-complete', 'getReportComplete', $postPerms);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
        ];
    }
}

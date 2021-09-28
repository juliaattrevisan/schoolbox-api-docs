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
        /* Anyone can post to the endpoint, for now
         *
         * ...but we should really be validating the Turnitin payloads before
         * blindly accepting them
         */
        $postPerms = null;

        $this->register('POST', '/upload-complete', 'postUploadComplete', $postPerms);
        $this->register('POST', '/report-complete', 'postReportComplete', $postPerms);
        $this->register('POST', '/report-updated', 'postReportUpdated', $postPerms);
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
            'turnitinMapper' => $this->getMapperFactory()->getLazy(Mapper::class)
        ];
    }
}

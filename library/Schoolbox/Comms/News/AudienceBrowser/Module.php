<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsAudienceBrowser';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        // Routes are always available. Results will vary depending on user and system config.
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        $this->register('GET', '/search', 'getSearch', $getPerms);
        $this->register('GET', '/list/{entityType}', 'getList', $getPerms);
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
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'db'               => $injector->lazyGet('databaseInternal'),
            'folderMapper'     => $this->getMapperFactory()->getLazy(Folder\Mapper::class),
            'audienceMapper'   => $this->getMapperFactory()->getLazy(Audience\Mapper::class),
            'roleAccessMapper' => $this->getMapperFactory()->getLazy(RoleAccessMapper::class),
        ];
    }
}

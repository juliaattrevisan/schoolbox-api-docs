<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Comms\News\Audience;
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
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'db'               => $injector->lazyGet('databaseInternal'),
            'audienceMapper'   => $this->getMapperFactory()->getLazy(Audience\Mapper::class),
            'folderMapper'     => $this->getMapperFactory()->getLazy(Folder\Mapper::class),
            'strategyFactory'  => $injector->lazyGet('commsNewsAudienceStrategyFactory'),
        ];
    }
}

<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
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
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('databaseInternal'),
            $this->getMapperFactory()->get(Audience\Mapper::class),
            $this->getMapperFactory()->get(Folder\Mapper::class),
            $this->injector->get('commsNewsAudienceStrategyFactory'),
        );
    }
}

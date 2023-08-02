<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Database\Connector\IConnector;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly as FolderTranslator;
use Schoolbox\Search\Api\Hydrator;
use Schoolbox\User\Formatter\Factory;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;
use Schoolbox\UserMapper;

/**
 * Module for the search API.
 */
class Module extends BaseModule
{
    protected $basename = 'searchApi';

    /**
     * @inheritdoc
     */
    public function registerRoutes(): void
    {
        $permission = new IsLoggedIn();
        $this->register(self::METHOD_GET, '', 'getSearch', $permission);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $config = $this->getConfig();
        $mapperFactory = $this->getMapperFactory();

        /** @var IConnector $database */
        $database = $this->injector->get('databaseInternalRead');

        // Need a user name formatter
        $userNameFormatter = (new Factory())->buildFromConfig($config);

        // Need a route generator
        $routeGenerator = $this->getRouter()->getGenerator();

        return new Controller(
            new Database\AggregateSource(
                $database,
                new Database\ResultType\User\QueryParamBuilder($config, $database->getQuoter()),
                new Database\ResultType\News\QueryParamBuilder($database->getQuoter()),
            ),
            new Hydrator\Aggregate(
                new Database\ResultType\User\Hydrator(
                    $mapperFactory->get(UserMapper::class),
                    $userNameFormatter,
                ),
                new Database\ResultType\News\Hydrator(
                    $mapperFactory->get(NewsMapper::class),
                    $routeGenerator,
                ),
            ),
            new Hydrator\Translator\FullResultToArray(
                new UserTranslator(),
                new FolderTranslator(0),
            ),
        );
    }
}

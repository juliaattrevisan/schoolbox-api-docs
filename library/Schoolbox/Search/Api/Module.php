<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Database\Connector\IConnector;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Forum\PostMapper;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly as FolderTranslator;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Tile\Mapper as TileMapper;
use Schoolbox\Tile\Layout\Mapper as TileLayoutMapper;
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
        $this->register(
            self::METHOD_GET,
            '',
            'getSearch',
            new Rule\IsLoggedIn(),
        );
        $this->register(
            self::METHOD_GET,
            '/folder/{folderId}',
            'getSearchWithinFolder',
            new Rule\HasHomepageAccess(
                UserAccess::ACCESS_READ,
                fn (array $params = []) => $params['folderId'] ?? null,
                Access::getInstance(),
            ),
            function (array $params) {
                return [
                    'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
                ];
            }
        );
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

        // Need a username formatter
        $userNameFormatter = (new Factory())->buildFromConfig($config);

        // Need a route generator
        $routeGenerator = $this->getRouter()->getGenerator();

        // Need a news article resolver
        /** @var ArticleResolver $articleResolver */
        $articleResolver = $this->injector->get('commsNewsArticleResolver');

        // Need an assessment mapper proxy
        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->injector->get('assessmentDataSource');

        // Need an authorizer.
        /** @var Authorizer $authorizer */
        $authorizer = $this->injector->get('authorizer');

        // Need an httpRequest.
        $httpRequest = $this->injector->get('httpRequest');

        $queryParamBuilders = [
            new Database\ResultType\User\QueryParamBuilder($config, $database->getQuoter()),
            new Database\ResultType\News\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Page\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\File\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Event\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Link\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Textbox\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Blog\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Tile\QueryParamBuilder($database->getQuoter()),
            new Database\ResultType\Forum\QueryParamBuilder($database->getQuoter()),
        ];

        $hydrators = [
            new Database\ResultType\User\Hydrator(
                $mapperFactory->get(UserMapper::class),
                $userNameFormatter,
                $config,
                $routeGenerator,
                $authorizer
            ),
            new Database\ResultType\News\Hydrator(
                $mapperFactory->get(NewsMapper::class),
                $articleResolver,
                $routeGenerator,
                $authorizer
            ),
            new Database\ResultType\Page\Hydrator(
                $mapperFactory->get(FolderMapper::class),
                Access::getInstance(),
                $routeGenerator,
                $authorizer
            ),
            new Database\ResultType\File\Hydrator(
                $mapperFactory->get(FileMapper::class),
                Access::getInstance(),
                new PrefixGenerator($this->getConfig()),
            ),
            new Database\ResultType\Event\Hydrator(
                $mapperFactory->get(UserMapper::class),
                $mapperFactory->get(FolderMapper::class),
            ),
            new Database\ResultType\Link\Hydrator(
                $mapperFactory->get(LinkMapper::class),
                Access::getInstance(),
            ),
            new Database\ResultType\Textbox\Hydrator(
                $mapperFactory->get(TextboxMapper::class),
                $mapperFactory->get(FolderMapper::class),
                Access::getInstance(),
                $routeGenerator,
                $authorizer
            ),
            new Database\ResultType\Blog\Hydrator(
                $mapperFactory->get(BlogMapper::class),
                Access::getInstance(),
            ),
            new Database\ResultType\Tile\Hydrator(
                $mapperFactory->get(TileMapper::class),
                $mapperFactory->get(TileLayoutMapper::class),
                $mapperFactory->get(FolderMapper::class),
                Access::getInstance(),
            ),
            new Database\ResultType\Forum\Hydrator(
                $mapperFactory->get(PostMapper::class),
                $mapperFactory->get(ThreadMapper::class),
                Access::getInstance(),
            ),
        ];

        if ($config->get(ConfigMapper::NAME_BADGES_ENABLED)) {
            $queryParamBuilders[] = new Database\ResultType\Badge\QueryParamBuilder($database->getQuoter());
            $hydrators[] = new Database\ResultType\Badge\Hydrator(
                $mapperFactory->get(BadgeMapper::class),
                new PrefixGenerator($this->getConfig()),
                $routeGenerator,
                $authorizer
            );
        }

        if ($config->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $queryParamBuilders[] = new Database\ResultType\Assessment\QueryParamBuilder($database->getQuoter());
            $hydrators[] = new Database\ResultType\Assessment\Hydrator(
                $assessmentMapperProxy,
                $mapperFactory->get(UserMapper::class),
                Access::getInstance(),
                $routeGenerator,
                $authorizer,
                $httpRequest
            );
        }

        return new Controller(
            new Database\AggregateSource($database, $queryParamBuilders),
            new Hydrator\Aggregate($hydrators),
            new Hydrator\Translator\FullResultToArray(
                new UserTranslator(),
                new FolderTranslator(0),
            ),
            $mapperFactory->get(UserMapper::class),
            $mapperFactory->get(FolderMapper::class),
            $database,
        );
    }
}

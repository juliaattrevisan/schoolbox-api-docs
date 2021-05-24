<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Authorizer;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Comms\News\Statistics
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsStatistics';

    /**
     * Hook method for setting up routes via self::register()
     */
    protected function registerRoutes(): void
    {
        /* Only comms officers and authors can view statistics and obtain their data
         * Supers, Comms Officers, Article Moderators
         *
         * Anyone with write access to the folder should be able to view the article's stats too
         */
        $viewStats = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\IsConfigOn(ConfigMapper::COMMS_STATISTICS_ENABLED, $this->getConfig()),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                    new Authorizer\Rule\IsArticleAuthor(),
                    new Authorizer\Rule\CanModerateAudience($this->getStrategyFactory())
                )
            );
        });

        $preloadArticle = function ($params) {
            return $this->preloadArticleWithAudience($params);
        };

        /* Json payload for the "Conversions, Impressions vs Time" graph
         */
        $this->register('GET', '/{articleId}/interactionsvstime', 'getInteractionsVsTime', $viewStats, $preloadArticle);

        /* Overview
         */
        $this->register('GET', '/{articleId}', 'getOverview', $viewStats, $preloadArticle);
        $this->register('GET', '/{articleId}/visitation', 'getVisitation', $viewStats, $preloadArticle);
        $this->register('GET', '/{articleId}/visitation/full', 'getFull', $viewStats, $preloadArticle);
        $this->register('GET', '/{articleId}/visitation/top10', 'getTop10', $viewStats, $preloadArticle);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'databaseInternal' => $injector->lazy(function () {
                return $this->getInjector()->get('databaseInternal');
            }),
            'statisticsMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
            'statisticsCumulativeMapper' => $this->getMapperFactory()->getLazy(Cumulative\Mapper::class),
            'userResolver' => $injector->lazyGet('commsNewsUserResolver'),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
        ];
    }


    /**
     * Preload an article instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadArticleWithAudience(array $params): array
    {
        if (\array_key_exists('article', $params)) {
            $article = $params['article'];
        } else {
            try {
                $article = $this->getArticleMapper()->findById($params['articleId']);
                $article->setAudience(News\Audience\Mapper::getInstance()->findForArticles([$article->getId()]));
            } catch (MapperException $e) {
                throw new NotFound("No article found for 'articleId' {$params['articleId']}", 0, $e);
            }
        }

        return [
            'article' => $article,
        ];
    }


    /**
     * @return \Schoolbox\Mapper|News\Mapper
     * @throws InvalidArgumentException
     */
    private function getArticleMapper(): News\Mapper
    {
        return $this->getMapperFactory()->get(News\Mapper::class);
    }


    /**
     * @return News\Audience\Segment\StrategyFactory|object
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function getStrategyFactory(): News\Audience\Segment\StrategyFactory
    {
        return $this->injector->get('commsNewsAudienceStrategyFactory');
    }
}

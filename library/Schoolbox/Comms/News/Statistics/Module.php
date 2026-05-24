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
use Alaress\Web\Controller\Base;
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
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('databaseInternal'),
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(Cumulative\Mapper::class),
            $this->injector->get('commsNewsUserResolver'),
            $this->getMapperFactory()->get(UserMapper::class),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
     * @return News\Mapper
     * @throws InvalidArgumentException
     */
    private function getArticleMapper(): News\Mapper
    {
        return $this->getMapperFactory()->get(News\Mapper::class);
    }


    /**
     * @return News\Audience\Segment\StrategyFactory
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    private function getStrategyFactory(): News\Audience\Segment\StrategyFactory
    {
        return $this->injector->get('commsNewsAudienceStrategyFactory');
    }
}

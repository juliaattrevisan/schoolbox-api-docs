<?php

/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\SaveForLater\Mapper as SaveForLaterMapper;
use Schoolbox\Comms\News\Authorizer;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsSaveForLater';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $canViewList = $this->getAuthRuleCanViewList();
        $canAdd      = $this->getAuthRuleCanAddSavedForLater();
        $canRemove   = $this->getAuthRuleCanRemoveSavedForLater();

        $preloadNews = function ($params) {
            return $this->preloadNews($params);
        };

        $preloadNewsWithAudience = function ($params) {
            return $this->preloadArticleWithAudience($params);
        };

        $this->register('GET', '', 'getList', $canViewList);
        $this->register('POST', '/{articleId}/add', 'postAdd', $canAdd, $preloadNewsWithAudience);
        $this->register('POST', '/{articleId}/remove', 'postRemove', $canRemove, $preloadNews);
    }

    /**
     * Preload the news article
     *
     * @param array $params
     * @return array
     */
    private function preloadNews(array $params): array
    {
        if (\array_key_exists('article', $params)) {
            $article = $params['article'];
        } else {
            try {
                $article = $this->getNewsMapper()->findById($params['articleId']);
            } catch (MapperException $e) {
                throw new NotFound($this->getErrorMapper()->getMessageById(ErrorMapper::ERROR_GENERIC_NOT_FOUND));
            }
        }

        return [
            'article' => $article,
        ];
    }

    /**
     * Preload the news article with audience hydrated
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws InvalidArgumentException
     * @throws NotFound
     * @throws \DomainException
     */
    private function preloadArticleWithAudience(array $params): array
    {
        $args = $this->preloadNews($params);
        /** @var Model $article */
        $article = $args['article'];
        $article->setAudience(Audience\Mapper::getInstance()->findForArticles([$article->getId()]));
        return $args;
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(SaveForLaterMapper::class),
            $this->getMapperFactory()->get(NewsMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Comms\News\File\Mapper::class),
            $this->injector->get('commsNewsArticleResolver'),
            $this->getMapperFactory()->get(UserAccessMapper::class),
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
     * @return Mapper|NewsMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getNewsMapper()
    {
        return $this->getMapperFactory()->get(NewsMapper::class);
    }


    /**
     * @return ErrorMapper
     * @throws InvalidArgumentException
     */
    private function getErrorMapper()
    {
        /** @var ErrorMapper $errorMapper */
        $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
        return $errorMapper;
    }


    /**
     * Returns an auth rule do determine if the user the user is logged in to view their 'save-for-later' list
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function getAuthRuleCanViewList(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn(ConfigMapper::_SAVE_FOR_LATER, $this->getConfig())
        );
    }

    /**
     * Returns a lazy auth rule do determine if a user can add an article to their 'save-for-later' list
     *
     * @return Rule\IAuthorizationRule
     */
    private function getAuthRuleCanAddSavedForLater(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\IsConfigOn(ConfigMapper::_SAVE_FOR_LATER, $this->getConfig()),
                $this->getAuthRuleCanRead()
            );
        });
    }


    /**
     * Returns a lazy auth rule do determine if a user can remove an article from their 'save-for-later' list
     *
     * @return Rule\IAuthorizationRule
     */
    private function getAuthRuleCanRemoveSavedForLater(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Callback(function (array $params = []) {
                    // Intentionally not catching mapper exception, that's a 5xx, not 4xx
                    if (!$this->getConfig()->get(ConfigMapper::_SAVE_FOR_LATER)) {
                        throw new InvalidPath('This feature has been disabled.');
                    }
                    return true;
                })
            );
        });
    }

    /**
     * Returns a lazy auth rule to determine, if a user can read the article
     * This rule set should be as same as $readerPerms in News/Module.php
     *
     * @return Rule\IAuthorizationRule
     */
    private function getAuthRuleCanRead(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            /** @var UserResolver $userResolver */
            $userResolver = $this->injector->get('commsNewsUserResolver');
            /** @var StrategyFactory $strategyFactory */
            $strategyFactory = $this->injector->get('commsNewsAudienceStrategyFactory');
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_READ),
                    new Authorizer\Rule\CanModerateAudience($strategyFactory),
                    new Authorizer\Rule\IsArticleAuthor(),
                    new Rule\All(
                        new Authorizer\Rule\IsPublishedOrExpired(),
                        new Rule\Any(
                            new Authorizer\Rule\IsShareable(),
                            new Authorizer\Rule\IsMemberOfArticleAudience($userResolver),
                            new Authorizer\Rule\HaveReadPermToAudience($strategyFactory)
                        )
                    )
                )
            );
        });
    }
}

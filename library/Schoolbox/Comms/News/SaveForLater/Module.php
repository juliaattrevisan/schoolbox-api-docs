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
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Comms\News\SaveForLater\Mapper as SaveForLaterMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;

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

        $this->register('GET', '', 'getList', $canViewList);
        $this->register('POST', '/{articleId}/add', 'postAdd', $canAdd, $preloadNews);
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
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'saveForLaterMapper' => $this->getMapperFactory()->getLazy(SaveForLaterMapper::class),
            'newsMapper' => $this->getMapperFactory()->getLazy(NewsMapper::class),
            'fileMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\Comms\News\File\Mapper::class),
        ];
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
     * @return Mapper|ErrorMapper
     * @throws InvalidArgumentException
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
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
                new Rule\Callback(function (array $params = []) {
                    // Intentionally not catching mapper exception, that's a 5xx, not 4xx
                    if (!$this->getConfig()->get(ConfigMapper::_SAVE_FOR_LATER)) {
                        throw new InvalidPath('This feature has been disabled.');
                    }
                    try {
                        if ($params['article']) {
                            $article = $params['article'];
                        } else {
                            $article = $this->getNewsMapper()->findById($params['articleId']);
                        }
                    } catch (MapperException $e) {
                        throw new InvalidPath("Article ({$params['articleId']}) not found", 0, $e);
                    }
                    if (!$article->isPublished() && !$article->isExpired()) {
                        throw new Unauthorized('Only published or expired articles may be saved for later');
                    }
                    // TODO, can the user see this article? Probably: $article->isSharable() || isUserInAudience($user, $article)
                    return true;
                })
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
}

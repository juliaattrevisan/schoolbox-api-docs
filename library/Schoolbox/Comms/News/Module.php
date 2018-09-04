<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNews';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {

        /* Anyone can spawn new articles, visit the archive or index
         */
        $viewPerms = new Rule\LazyCallback(function() {
            return new Rule\All(
                new Rule\IsLoggedIn()
            );
        });

        /* For reading articles, you need to be logged in and be either the Author;
         * or the article is publicly accessible
         */
        $readerPerms = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Authorizer\Rule\IsAuthorOrModerator($this->getNewsMapper()),
                    new Authorizer\Rule\IsPubliclyReadable($this->getNewsMapper(), $this->getInjector()->get('userResolver'))
                )
            );
        });

        /* The current user modify the article
         * Supers, Comms Officers, Article Authors
         */
        $modifyPerms = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Authorizer\Rule\IsAuthorOrModerator($this->getNewsMapper())
            );
        });

        $preloadArticle = function ($params) {
            return $this->preloadArticle($params);
        };

        /* Create news article
         */
        $this->register('GET', '/create', 'getCreate', $viewPerms);
        $this->register('POST', '/create', 'postCreate', $viewPerms);

        /* (async) Get a list of topics to chooze
         */
        $this->register('GET', '/topics', 'getTopics', $viewPerms);

        /* Update news article
         */
        $this->register('GET', '/{articleId:\d+}/update', 'getUpdate', $modifyPerms, $preloadArticle);
        $this->register('POST', '/{articleId:\d+}', 'postUpdate', $modifyPerms, $preloadArticle);

        /* Delete article
         */
        $this->register('POST', '/{articleId:\d+}/delete', 'postDelete', $modifyPerms, $preloadArticle);

        /* View news article
         * TODO: SA-7312, check draft, publication, author status
         */
        $this->register('GET', '/{articleId:\d+}', 'getDetail', $readerPerms, $preloadArticle);

        /* (async) Get a list of articles audienced to me, by topic
         */
        $this->register('GET', '/list{/year,month}', 'getListByTopic', $viewPerms);

        /* News Archive
         */
        $this->register('GET', '/archive{/year,month}', 'getArchive', $viewPerms);

        // Index of articles authored by the logged in user
        $this->register('GET', '/me', 'getAuthored', $viewPerms);

        // (async) Get a list of articles a user has authored
        $this->register('GET', '/me/articles', 'getAuthoredArticles', $viewPerms);

        /* Find out who will receive this article
         */
        $this->register('GET', '/{articleId:\d+}/recipients', 'getRecipients', $modifyPerms, $preloadArticle);

        /* Index of articles audienced to you
         */
        $this->register('GET', '', 'getIndex', $viewPerms);
    }


    /**
     * Preload the news article
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadArticle(array $params) :array
    {
        try {
            $article = $this->getNewsMapper()->findById($params['articleId']);
        } catch (MapperException $e) {
            throw new NotFound('Unknown news article.');
        }

        return [
            'article' => $article,
        ];
    }

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getNewsMapper()
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }


    /**
     * @return Mapper|ErrorMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
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
            'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            'newsMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
            'policyMapper' => $this->getMapperFactory()->getLazy(Policy\Mapper::class),
            'topicMapper' => $this->getMapperFactory()->getLazy(TopicMapper::class),
            'saveForLaterMapper' => $this->getMapperFactory()->getLazy(SaveForLater\Mapper::class),
            'statisticsMapper' => $this->getMapperFactory()->getLazy(Statistics\Mapper::class),
            'statisticsCumulativeMapper' => $this->getMapperFactory()->getLazy(Statistics\Cumulative\Mapper::class),
            'audienceMapper' => $this->getMapperFactory()->getLazy(Audience\Mapper::class),
            'roleAccessMapper' => $this->getMapperFactory()->getLazy(Access\RoleAccessMapper::class),
            'folderMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\Resource\Folder\Mapper::class),
            'databaseInternal' => $injector->lazyGet('databaseInternal'),

            /* Hyperdrive injection
             */
            'articleResolver' => $injector->lazyGet('articleResolver'),
            'userResolver' => $injector->lazyGet('userResolver'),
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
}

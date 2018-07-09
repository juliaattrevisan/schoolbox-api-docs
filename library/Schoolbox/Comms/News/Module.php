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
use Schoolbox\Role;
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
        /* For viewing articles you just need to be logged in and controller,
         * I suppose, deals with whether you are in the audience
         */
        $viewPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        /* Any staff can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );

        /* Any staff can receive data from the form
         */
        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );

        $preloadNews = function ($params) {
            return $this->preloadNews($params);
        };

        /* Create news article
         */
        $this->register('GET', '/create', 'getCreate', $getPerms);
        $this->register('POST', '/create', 'postCreate', $postPerms);

        /* Update news article
         */
        $this->register('GET', '/{articleId:\d+}/update', 'getUpdate', $getPerms, $preloadNews);
        $this->register('POST', '/{articleId:\d+}', 'postUpdate', $getPerms, $preloadNews);

        /* View news article
         */
        $this->register('GET', '/{articleId:\d+}', 'getDetail', null, $preloadNews);

        /* Delete article
         */
        $this->register('POST', '/{articleId:\d+}/delete', 'postDelete', null, $preloadNews);

        /* Get a list of topics to chooze
         */
        $this->register('GET', '/topics', 'getTopics', $getPerms);

        /* Get a list of articles audienced to me, by topic
         */
        $this->register('GET', '/list/{topicId:\d+}/{limit:\d+}', 'getListByTopic', $viewPerms);

        /* Index of articles audienced to you
         */
        $this->register('GET', '{/slug}', 'getIndex', $viewPerms);
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
    private function preloadNews(array $params) :array
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

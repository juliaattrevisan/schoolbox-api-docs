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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
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
    protected function registerRoutes()
    {
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

        $preloadNews = function ($params) { return $this->preloadNews($params); };

        $this->register('GET', '/create', 'getCreate', $getPerms);
        $this->register('GET', '/update/{newsId}', 'getUpdate', $getPerms, $preloadNews);
        $this->register('POST', '/create', 'postCreate', $postPerms);
        $this->register('POST', '/update/{newsId}', 'postUpdate', $getPerms, $preloadNews);
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
            $news = $this->getNewsMapper()->findById($params['newsId']);
        }
        catch (MapperException $e) {
            throw new NotFound(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        return [
            'news' => $news,
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
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'errorMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\Error\Mapper::class),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
            'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
            'newsMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
            'policyMapper' => $this->getMapperFactory()->getLazy(Policy\Mapper::class),
        ];
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}

<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News\Mapper as NewsMapper;
use Schoolbox\Comms\News\SaveForLater\Mapper as SaveForLaterMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;


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
        $perms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        $preloadNews = function ($params) {
            return $this->preloadNews($params);
        };

        $this->register('GET', '', 'getList', $perms);
        $this->register('POST', '/{newsId}', 'postActivate', $perms, $preloadNews);
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
        } catch (MapperException $e) {
            throw new NotFound($this->getErrorMapper()->getMessageById(ErrorMapper::ERROR_GENERIC_NOT_FOUND));
        }

        return [
            'news' => $news,
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
            'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            'saveForLaterMapper' => $this->getMapperFactory()->getLazy(SaveForLaterMapper::class),
        ];
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getNewsMapper()
    {
        return $this->getMapperFactory()->get(NewsMapper::class);
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

}

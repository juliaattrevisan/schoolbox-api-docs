<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Feed;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsFeed';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $preloadUserAndFolder = function ($params) {
            return $this->preloadUserAndFolder($params);
        };

        $this->register('GET', '/{key}{/folderId}', 'getFeed', null, $preloadUserAndFolder);
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUserAndFolder(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findOneByColumn('key', $params['key']);

            $folder = null;

            if ($params['folderId']) {
                $folder = $this->getFolderMapper()->findOneByColumn('id', $params['folderId']);
            }
        }
        catch (MapperException $e) {
            throw new Unauthorized();
        }

        return [
            'user' => $user,
            'folder' => $folder,
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
            'newsMapper'                 => $this->getMapperFactory()->getLazy(News\Mapper::class),
            'topicMapper'                => $this->getMapperFactory()->getLazy(Topic\Mapper::class),
            'saveForLaterMapper'         => $this->getMapperFactory()->getLazy(News\SaveForLater\Mapper::class),
            'statisticsMapper'           => $this->getMapperFactory()->getLazy(News\Statistics\Mapper::class),
            'statisticsCumulativeMapper' => $this->getMapperFactory()->getLazy(News\Statistics\Cumulative\Mapper::class),
            'databaseInternal'           => $injector->lazyGet('databaseInternal'),
            'articleResolver'            => $injector->lazyGet('commsNewsArticleResolver'),
            'access'                     => $this->getMapperFactory()->getLazy(Access::class),
            'commentMapper'              => $this->getMapperFactory()->getLazy(\Schoolbox\Discussion\Comment\Mapper::class),
            'audienceMapper'             => $this->getMapperFactory()->getLazy(News\Audience\Mapper::class),
        ];
    }


    /**
     * @return Mapper|Folder\Mapper
     * @throws InvalidArgumentException
     */
    private function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}

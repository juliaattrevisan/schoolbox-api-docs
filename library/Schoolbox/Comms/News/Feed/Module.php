<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Feed;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Authorizer;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsFeed';

    /**
     * Hook method for setting up routes via self::register()
     */
    protected function registerRoutes(): void
    {
        $preloadUser          = function ($params) {
            return $this->preloadUser($params);
        };
        $preloadFolderAndUser = function ($params) {
            return array_merge(
                $this->preloadUser($params),
                $this->preloadFolder($params)
            );
        };

        // Must be SU, or have News service READ+ perm, or READ+ access to folder
        $canReadFolderNews = new Rule\LazyCallback(static function () {
            return new Rule\Callback(static function (array $params): bool {
                /** @var User $user */
                $user = $params['user'];
                if ($user->isSuperuser()) {
                    return true;
                }
                if (Access::getInstance()->getAccessLevel($user, UserAccess::SERVICE_NEWS) >= UserAccess::ACCESS_READ) {
                    return true;
                }
                if (Access::getInstance()->getFolderAccess($user, (int)$params['folderId']) >= UserAccess::ACCESS_READ) {
                    return true;
                }
                throw new Unauthorized("User with key '{$user->getKey()}' does not have access to news RSS for folder {$params['folderId']}");
            });
        });

        // Note there is no route auth here, it does not require a session, simply knowing a user.key suffices.
        $this->register('GET', '/{key}', 'getFeedForUser', null, $preloadUser);

        // User identified by 'key' (not session user), must be SU, or have News service READ+ perm, or READ+ access to folder
        $this->register('GET', '/{key}/{folderId:\d+}', 'getFeedForFolder', $canReadFolderNews, $preloadFolderAndUser);
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws Unauthorized
     */
    private function preloadUser(array $params): array
    {
        if ($params['user'] instanceof User) {
            return ['user' => $params['user']];
        }
        try {
            return ['user' => $this->getUserMapper()->findOneByColumn('key', $params['key'])];
        } catch (MapperException $e) {
            throw new Unauthorized("No user found for key: '{$params['key']}'", $e->getCode(), $e);
        }
    }


    /**
     * Preload a Folder instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws Unauthorized
     */
    private function preloadFolder(array $params): array
    {
        if ($params['folder'] instanceof Folder\Model) {
            return ['folder' => $params['folder']];
        }
        try {
            return ['folder' => $this->getFolderMapper()->findOneByColumn('id', $params['folderId'])];
        } catch (MapperException $e) {
            throw new Unauthorized("No folder found for id: '{$params['folderId']}'", $e->getCode(), $e);
        }
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
            'articleResolver'            => $injector->lazyGet('commsNewsArticleResolver'),
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

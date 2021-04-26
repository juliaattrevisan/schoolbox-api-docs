<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'geosnapshotApi';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        $preloadUser = function ($params) { return $this->preloadUser($params); };
        $preloadUserAndUrl = function ($params) { return $this->preloadUserAndUrl($params); };

        $this->register('GET', '/user/{id}', 'getStudentPhotosUrl', $getPerms, $preloadUser);
        $this->register('GET', '/user/{id}/{url}', 'getStudentPhotos', $getPerms, $preloadUserAndUrl);
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUserAndUrl(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findById($params['id']);
        }
        catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'viewee' => $user,
            'url' => Redirect::decode($params['url'])
        ];
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUser(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findById($params['id']);
        }
        catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'viewee' => $user,
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
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class)
        ];
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}

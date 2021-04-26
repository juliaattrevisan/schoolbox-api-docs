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


/**
 * Class Module
 *
 * @package Schoolbox\Geosnapshot
 */
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
        $preloadUser = function ($params) { return $this->preloadUser($params); };
        $preloadUserAndUrl = function ($params) { return $this->preloadUserAndUrl($params); };

        $canGetStudentPhotosUrl = $this->canGetStudentPhotosUrl();
        $canGetStudentPhotos = $this->canGetStudentPhotosUrl();

        $this->register('GET', '/user/{id}', 'getStudentPhotosUrl', $canGetStudentPhotosUrl, $preloadUser);
        $this->register('GET', '/user/{id}/{url}', 'getStudentPhotos', $canGetStudentPhotos, $preloadUserAndUrl);
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
        } catch (MapperException $e) {
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
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'viewee' => $user,
        ];
    }


    /**
     * Preload a User instance
     *
     * @return Rule\IAuthorizationRule
     */
    private function canGetStudentPhotosUrl(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Callback(function (array $params, \Schoolbox\User $sessionUser) {
                    try {
                        $viewee = $this->getUserMapper()->findById($params['id']);
                    } catch (MapperException $e) {
                        throw new NotFound();
                    }

                    /* No Geosnapshot? No trabajo
                     *
                     * One or more misconfigurations or access controls could have caused this
                     * but all result in nothing to see for the current user
                     */
                    $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
                        ->isSatisifiedBy(
                            $this->getConfig()->get('geosnapshot_api_key'),
                            $this->getConfig()->get('geosnapshot_enabled'),
                            $sessionUser,
                            $viewee
                        );

                    if (!$isGeosnapshotAvailable) {
                        throw new NotFound();
                    }

                    return [
                        'viewee' => $viewee,
                    ];
                })
            );
        });
    }


    /**
     * Preload a User instance with the url from Geosnapshot
     *
     * @return Rule\IAuthorizationRule
     */
    private function canGetStudentPhotos(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Callback(function (array $params, \Schoolbox\User $sessionUser) {
                    try {
                        $viewee = $this->getUserMapper()->findById($params['id']);
                    } catch (MapperException $e) {
                        throw new NotFound();
                    }

                    /* No Geosnapshot? No trabajo
                     *
                     * One or more misconfigurations or access controls could have caused this
                     * but all result in nothing to see for the current user
                     */
                    $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
                        ->isSatisifiedBy(
                            $this->getConfig()->get('geosnapshot_api_key'),
                            $this->getConfig()->get('geosnapshot_enabled'),
                            $sessionUser,
                            $viewee
                        );

                    if (!$isGeosnapshotAvailable) {
                        throw new NotFound();
                    }

                    return [
                        'viewee' => $viewee,
                        'url' => Redirect::decode($params['url'])
                    ];
                })
            );
        });
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

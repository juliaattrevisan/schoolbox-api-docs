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
use Alaress\Web\Controller\Base;
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
        $preloadUserAndUrl = function ($params) {
            return $this->preloadUserAndUrl($params);
        };

        $canGetStudentPhotos = $this->canGetStudentPhotosUrl();

        $this->register('GET', '/user/{id}', 'getStudentPhotos', $canGetStudentPhotos, $preloadUserAndUrl);
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

            /* Only students can be a viewee; parents and staff don't get pictures
             */
            if (!$user->getRole()->isStudent()) {
                throw new NotFound();
            }
        } catch (MapperException $e) {
            /* User doesn't really exist
             */
            throw new NotFound();
        }

        return [
            'viewee' => $user,
            'url' => isset($params['url']) ? Redirect::decode($params['url']) : '',
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
                    if (!isset($params['id'])) {
                        throw new NotFound();
                    }
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->injector->get('filesystem.storageProxy'),
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
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}

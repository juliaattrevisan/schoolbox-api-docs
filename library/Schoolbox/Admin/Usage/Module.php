<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Redis;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Example
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminUsage';

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
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL)
            )
        );

        $preloadUser = function (array $params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '', 'getIndex', $getPerms);

        /* Global Usage payload endpoints
         */
        $this->register('GET', '/system', 'getSystemPayload', $getPerms);
        $this->register('GET', '/role', 'getRolePayload', $getPerms);
        $this->register('GET', '/total', 'getTotalPayload', $getPerms);
        $this->register('GET', '/user', 'getUserPayload', $getPerms);
        $this->register('GET', '/type', 'getFileTypePayload', $getPerms);
        $this->register('POST', '/regenerate', 'postRegenerateUsage', $getPerms);

        /* Specific Usage payload endpoints
         */
        $this->register('GET', '/specific/{userId}', 'getSpecificUser', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/system', 'getSpecificUserSystemPayload', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/total', 'getSpecificUserTotalPayload', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/file', 'getSpecificUserFilePayload', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/type', 'getSpecificUserFileTypePayload', $getPerms, $preloadUser);
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
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (Mapper\Exception\Deleted $e) {
            $user = $e->getDeletedObject();
        } catch (Mapper\Exception $e) {
            throw new NotFound();
        }

        return [
            'user' => $user,
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->injector->get('databaseInternalRead'),
            Redis::getCacheInstance()
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

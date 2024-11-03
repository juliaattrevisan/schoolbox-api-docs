<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Admin\Profile\Editor\Payload;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Profile\Button\Campus;
use Schoolbox\Profile\Button\Role;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;

/**
 * The module for managing custom profile buttons through the admin section.
 *
 * @package Schoolbox\Admin\Profile\Button
 */
class Module extends BaseModule
{
    protected $basename = 'adminProfileButton';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
            )
        );

        $preloadRoleType = function ($params) {
            return $this->preloadRoleType($params);
        };

        $preloadRoleTypeDefault = function ($params) {
            return $this->preloadRoleTypeDefault($params);
        };

        $this->register('GET', '{/roleType}', 'getIndex', $perms, $preloadRoleTypeDefault);
        $this->register('GET', '/create/{roleType}', 'getCreate', $perms, $preloadRoleType);
        $this->register('GET', '/modify/{id}', 'getModify', $perms);
        $this->register('POST', '/delete', 'postDelete', $perms);
        $this->register('POST', '/order', 'postOrder', $perms);
        $this->register('POST', '/modify', 'postModify', $perms);
    }

    /**
     * Check that a valid and supported roletype was provided;
     * Use a default value instead, if omitted
     *
     * @param array $params
     * @return array
     * @throws NotFound
     */
    private function preloadRoleTypeDefault(array $params): array
    {
        $roleType = $params['roleType'] ?? \Schoolbox\Role::STUDENT;

        if (!isset(Payload::CONFIG_ROLETYPE[$roleType])) {
            throw new NotFound('Roletype not provided', 400);
        }

        return [
            'roleType' => $roleType,
        ];
    }

    /**
     * Check that a valid and supported roletype was provided
     *
     * @param array $params
     * @return array
     * @throws NotFound
     */
    private function preloadRoleType(array $params): array
    {
        $roleType = $params['roleType'] ?? null;

        if (!isset(Payload::CONFIG_ROLETYPE[$roleType])) {
            throw new NotFound('Roletype not provided', 400);
        }

        return [
            'roleType' => $params['roleType'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(IconMapper::class),
            $this->getMapperFactory()->get(ProfileButtonMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(Campus\Availability\Mapper::class),
            $this->getMapperFactory()->get(Role\Availability\Mapper::class),
            $this->getMapperFactory()->get(Campus\Visibility\Mapper::class),
            $this->getMapperFactory()->get(Role\Visibility\Mapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}

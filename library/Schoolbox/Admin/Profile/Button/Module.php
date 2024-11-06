<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Map;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
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
        /* Keep an eye out on Granular Permissions epic SA-18424
         * Hopefully there isn't a service permission that affects us here
         * and we can continue to assume Superusers Only
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '', 'getIndex', $perms);
        $this->register('GET', '/create', 'getCreate', $perms);
        $this->register('GET', '/modify/{id}', 'getModify', $perms);
        $this->register('POST', '/delete', 'postDelete', $perms);
        $this->register('POST', '/order', 'postOrder', $perms);
        $this->register('POST', '/modify', 'postModify', $perms);
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

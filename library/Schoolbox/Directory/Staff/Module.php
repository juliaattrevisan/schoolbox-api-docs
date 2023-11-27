<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Directory\Staff;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'staffDirectory';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            /* Staff Directory must be enabled in admin
             */
            new Rule\IsConfigOn(ConfigMapper::NAME_REPORT_STAFF_DIRECTORY_ENABLED, $this->getConfig()),
            new Rule\Any(
                /* If you're a parent and report_staff_access is truthy,
                 * You can go in (this config allows parents to see staff details)
                 */
                new Rule\All(
                    new Rule\HasRole(\Schoolbox\Role::PARENT),
                    new Rule\IsConfigOn(ConfigMapper::NAME_REPORT_STAFF_ACCESS, $this->getConfig())
                ),
                /* Or, if you're a staff member whose role has View Staff Directory,
                 * You can go in
                 */
                new Rule\All(
                    new Rule\HasRole(\Schoolbox\Role::STAFF),
                    new Rule\HasRolePermission(\Schoolbox\Role::PERMISSION_VIEW_STAFF_DIRECTORY, Role::STAFF)
                ),
            )
        );

        $this->register('GET', '', 'getList', $getPerms);
        $this->register('GET', '/payload', 'getPayload', $getPerms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            new \External_User(),
        );
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

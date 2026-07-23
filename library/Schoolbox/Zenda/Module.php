<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'zenda';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Feature flag must be enabled (NAME_ZENDA_ENABLED)
         * Zenda must be active (NAME_ZENDA_ACTIVE)
         * Current user must be a parent
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn(ConfigMapper::NAME_ZENDA_ENABLED, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_ZENDA_ACTIVE, $this->getConfig()),
            new Rule\HasRole(Role::PARENT),
        );

        $this->register('GET', '/redirect', 'getUrl', $getPerms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
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

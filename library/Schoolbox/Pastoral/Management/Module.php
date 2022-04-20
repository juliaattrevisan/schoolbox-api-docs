<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'pastoralManagement';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        // Define routes and permissions

        $permissionPastoralModeratorWrite = new HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_WRITE);

        $this->registerRoutesArray([
            'GET'  => [
                ''                  => ['getGroups'        => $permissionPastoralModeratorWrite],
                '/modify{/groupId}' => ['getGroupsModify'  => $permissionPastoralModeratorWrite],
            ],
            'POST' => [
                '/delete'           => ['postGroupsDelete' => $permissionPastoralModeratorWrite],
                '/modify'           => ['postGroupsModify' => $permissionPastoralModeratorWrite],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(Pastoral\Group\Mapper::class),
            $this->getMapperFactory()->get(Pastoral\Group\Member\Mapper::class),
            $this->getMapperFactory()->get(PastoralAccessMapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                $returnArray = [];

                // Load group record

                if (\array_key_exists('groupId', $params)) {
                    if (($params['group'] ?? null) instanceof Pastoral\Group\Model) {
                        $returnArray['group'] = $params['group'];
                    } else {
                        $groupMapper          = $this->getMapperFactory()->get(Pastoral\Group\Mapper::class);
                        $returnArray['group'] = $params['groupId'] ? $groupMapper->findById($params['groupId']) : $groupMapper->createModel();
                    }
                }

                return $returnArray;
            }
        );
    }
}

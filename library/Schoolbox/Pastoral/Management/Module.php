<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;

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
     * {@inheritdoc}
     * @throws InvalidArgumentException If invalid mapper class specified
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $mappers = [
            'userMapper'                => $this->getMapperFactory()->getLazy(UserMapper::class),
            'pastoralAccessMapper'      => $this->getMapperFactory()->getLazy(Pastoral\Access\Mapper::class),
            'pastoralGroupMapper'       => $this->getMapperFactory()->getLazy(Pastoral\Group\Mapper::class),
            'pastoralGroupMemberMapper' => $this->getMapperFactory()->getLazy(Pastoral\Group\Member\Mapper::class),
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
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

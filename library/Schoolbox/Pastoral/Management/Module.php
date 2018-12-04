<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Pastoral\SubType\Mapper as SubTypeMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;
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
                '/audience'                                             => ['getAudiences' => $permissionPastoralModeratorWrite],
                '/audience/type/{typeId}/modify'                        => ['getAudiencesModifyNoSub' => $permissionPastoralModeratorWrite],
                '/audience/type/{typeId}/subtype/{subtypeId}/modify'    => ['getAudiencesModify' => $permissionPastoralModeratorWrite],
                '/groups'                                               => ['getGroups' => $permissionPastoralModeratorWrite],
                '/groups/modify{/groupId}'                              => ['getGroupsModify' => $permissionPastoralModeratorWrite],
            ],
            'POST' => [
                '/groups/delete'                                        => ['postGroupsDelete'      => $permissionPastoralModeratorWrite],
                '/groups/modify'                                        => ['postGroupsModify'      => $permissionPastoralModeratorWrite],
                '/audience/type/{typeId}/modify'                        => ['postAudienceUpdateNoSub' => $permissionPastoralModeratorWrite],
                '/audience/type/{typeId}/subtype/{subtypeId}/modify'    => ['postAudienceUpdate' => $permissionPastoralModeratorWrite],
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
            'pastoralAudienceMapper'    => $this->getMapperFactory()->getLazy(Pastoral\Audience\Mapper::class),
            'roleMapper'                => $this->getMapperFactory()->getLazy(RoleMapper::class),
            'pastoralTypeMapper'        => $this->getMapperFactory()->getLazy(Pastoral\Type\Mapper::class),
            'pastoralSubTypeMapper'     => $this->getMapperFactory()->getLazy(Pastoral\SubType\Mapper::class),
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
        $this->addRoutePreload($matcher, '.*',
            function ($params) {
                $returnArray = [];

                // Load group record

                if (array_key_exists('groupId', $params)) {
                    /** @var GroupMapper $groupMapper */
                    $groupMapper          = $this->getMapperFactory()->get(GroupMapper::class);
                    $returnArray['group'] = $groupMapper->findById($params['groupId']);
                }
                if (array_key_exists('typeId', $params)) {
                    /** @var GroupMapper $groupMapper */
                    $typeMapper          = $this->getMapperFactory()->get(PastoralTypeMapper::class);
                    $returnArray['type'] = $typeMapper->findById($params['typeId']);

                }
                if (array_key_exists('subtypeId', $params)) {
                    /** @var GroupMapper $groupMapper */
                    $subTypeMapper          = $this->getMapperFactory()->get(SubTypeMapper::class);
                    $returnArray['subType'] = $subTypeMapper->findById($params['subtypeId']);
                }

                return $returnArray;
            }
        );
    }
}

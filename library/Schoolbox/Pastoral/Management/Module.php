<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Management;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModeratorWrite;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Role;
use Twig_Loader_Chain;

// These mappers are injected into controllers where required

use Schoolbox\UserMapper;

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

        $ruleStaff = new Rule\HasRole(Role::STAFF);
        $rulePastoralModeratorWrite = new Rule\All(
            $ruleStaff,
            new Rule\IsLoggedIn(),
            new IsPastoralModeratorWrite()
        );

        $this->registerRoutesArray([
            'GET' => [
                '/groups' => ['getGroups' => $rulePastoralModeratorWrite],
                '/groups/modify{/groupId}' => ['getGroupsModify' => $rulePastoralModeratorWrite],
            ],
            'POST' => [
                '/groups/delete' => ['postGroupsDelete' => $rulePastoralModeratorWrite],
                '/groups/modify' => ['postGroupsModify' => $rulePastoralModeratorWrite],
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'userMapper' => UserMapper::getInstance(),
            'pastoralMapper' => PastoralMapper::getInstance(),
            'pastoralSeverityMapper' => PastoralSeverityMapper::getInstance(),
            'pastoralTypeMapper' => PastoralTypeMapper::getInstance(),
            'pastoralAccessMapper' => PastoralAccessMapper::getInstance(),
            'pastoralTagMapper' => PastoralTagMapper::getInstance()
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
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
                    try {
                        /** @var GroupMapper $groupMapper */
                        $groupMapper = $this->getMapperFactory()->get(GroupMapper::class);
                        $returnArray['group'] = $groupMapper->findById($params['groupId']);
                    } catch (MapperException $e) {
                    }
                }

                return $returnArray;
            }
        );
    }
}

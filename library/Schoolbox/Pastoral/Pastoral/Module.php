<?php
/**
 * Module definition for pastoral actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Pastoral\Authorizer\Rule\IsInAudience;
use Schoolbox\Pastoral\Authorizer\Rule\IsOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsParentAccessAllowed;
use Schoolbox\Pastoral\Authorizer\Rule\IsParentOfStudent;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModerator;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModeratorWrite;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'pastoral';

    /**
     * Module constructor.
     * @param ContainerInterface $injector
     * @param string $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());

        parent::__construct($injector, $basename);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/report-builder' => 'getReportBuilder',
                '/report' => 'getReport',
                '/report-print' => 'getReportPrint',
                '/insert/{id}' => 'getInsert',
                '/update/{id}' => 'getUpdate',
                '/{studentId}/{id}' => 'getRecord',
                '/{studentId}' => 'getIndex',
            ],
            'POST' => [
                '/save' => 'postSave',
                '/comment' => 'postComment',
                '/comment-delete' => 'postCommentDelete',
                '/delete' => 'postDelete',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the item to params, if box_id is in the route.

        $userMapper = $this->getUserMapper();
        $pastoralMapper = $this->getPastoralMapper();
        $pastoralAccessMapper = $this->getPastoralAccessMapper();

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($userMapper, $pastoralMapper, $pastoralAccessMapper) {

                $returnArray = [];

                // Load student details

                if (array_key_exists('studentId', $params)) {
                    // Load in the students details
                    $returnArray['student'] = $userMapper->findById($params['studentId']);
                }

                // Load record and access details

                if (array_key_exists('id', $params)) {
                    try {
                        // Load in the pastoral record
                        $returnArray['record'] = $pastoralMapper->findById($params['id']);

                        // Load in the access records
                        $returnArray['access'] = $pastoralAccessMapper->findByColumn('pastoral_id', $params['id']);

                        // Work out if they're allowed private access
                        $returnArray['privateAllowed'] = $this->getPastoralMapper()->hasPrivatePermission($returnArray['record']);
                    } catch (MapperException $e) {
                    }
                }

                return $returnArray;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $permissionViewRecord = new Rule\All(
        // Must be logged in
            new Rule\IsLoggedIn(),

            // And either...
            new Rule\Any
            (
            // Superusers and moderators get a free pass
                new Rule\Any
                (
                    new Rule\IsSuperuser(),
                    new IsPastoralModerator()
                ),

                // Otherwise...
                new Rule\Any(

                // Staff members that are in the audience
                    new Rule\All
                    (
                        new IsInAudience(),
                        new Rule\HasRole(Role::STAFF)
                    ),

                    // Or parents of the child in the audience (and parent access is allowed in Schoolbox config)
                    new Rule\All
                    (
                        new Rule\HasRole(Role::PARENT),
                        new IsParentAccessAllowed(),
                        new IsParentOfStudent(),
                        new IsInAudience()
                    )
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getRecord', $permissionViewRecord);

        // Only staff can create reports, insert records, or post comments

        $permissionStaff = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any
            (
                new Rule\HasRole(Role::STAFF)
            )
        );

        $this->addRoutePermission($authorizer,
            'getReportBuilder|getReport|getReportPrint|getInsert|postComment|postCommentDelete', $permissionStaff);

        // Only staff, or parents of the student can view a students pastoral care record list page

        $permissionIndex = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any
            (
                new Rule\HasRole(Role::STAFF),
                new Rule\All
                (
                    new Rule\HasRole(Role::PARENT),
                    new IsParentOfStudent(),
                    new IsParentAccessAllowed()
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getIndex', $permissionIndex);

        // Only staff that are superusers, pastoral moderators (with write perms) or the owner of a record can update it

        $permissionUpdate = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\All
            (
            // Must be a staff member
                new Rule\HasRole(Role::STAFF),

                // And either superuser, moderator or owner of record
                new Rule\Any
                (
                    new Rule\IsSuperuser(),
                    new IsPastoralModeratorWrite(),
                    new IsOwner()
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getUpdate|postSave|postDelete', $permissionUpdate);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }

    /** Here be getters and setters */

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper()
    {
        return $this->pastoralAccessMapper;
    }

    /**
     * @param PastoralAccessMapper $mapper
     * @return $this
     */
    public function setPastoralAccessMapper(PastoralAccessMapper $mapper)
    {
        $this->pastoralAccessMapper = $mapper;
        return $this;
    }

    /**
     * @return PastoralMapper
     */
    public function getPastoralMapper()
    {
        return $this->pastoralMapper;
    }

    /**
     * @param PastoralMapper $mapper
     * @return $this
     */
    public function setPastoralMapper(PastoralMapper $mapper)
    {
        $this->pastoralMapper = $mapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $mapper
     * @return $this
     */
    public function setUserMapper(UserMapper $mapper)
    {
        $this->userMapper = $mapper;
        return $this;
    }

    /** @var UserMapper */
    protected $userMapper;

    /** @var PastoralMapper */
    protected $pastoralMapper;

    /** @var PastoralAccessMapper */
    protected $pastoralAccessMapper;
}

<?php
/**
 * Module definition for pastoral actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Pastoral\Authorizer\Rule\IsCommentOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsInAudience;
use Schoolbox\Pastoral\Authorizer\Rule\IsOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsParentAccessAllowed;
use Alaress\Authorizer\Rule\IsParentOfStudent;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModerator;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModeratorWrite;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
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
        $this->setPastoralCommentMapper(PastoralCommentMapper::getInstance());

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
                '/insert/{studentId}' => 'getInsert',
                '/update/{recordId}' => 'getUpdate',
                '/{studentId}/{recordId}' => 'getRecord',
                '/{studentId}' => 'getIndex',
            ],
            'POST' => [
                '/insert' => 'postInsert',
                '/update/{recordId}' => 'postUpdate',
                '/delete/{recordId}' => 'postDelete',
                '/comment-save/{recordId}' => 'postCommentSave',
                '/comment-delete/{commentId}' => 'postCommentDelete',
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
        // Preload required data for the various routes

        $userMapper = $this->getUserMapper();
        $pastoralMapper = $this->getPastoralMapper();
        $pastoralAccessMapper = $this->getPastoralAccessMapper();
        $pastoralCommentMapper = $this->getPastoralCommentMapper();
        
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($userMapper, $pastoralMapper, $pastoralAccessMapper, $pastoralCommentMapper) {

                $returnArray = [];

                // Load student record

                if (array_key_exists('studentId', $params)) {
                    $returnArray['student'] = $userMapper->findWithSubjects($params['studentId']);
                }

                // Load comment

                if (array_key_exists('commentId', $params)) {
                    $returnArray['comment'] = $pastoralCommentMapper->findById($params['commentId']);
                }

                // Load pastoral record and associated access details

                if (array_key_exists('recordId', $params)) {
                    try {
                        // Load in the pastoral record
                        $returnArray['record'] = $pastoralMapper->findById($params['recordId']);

                        // Load in the access records
                        $returnArray['access'] = $pastoralAccessMapper->findByColumn('pastoral_id', $params['recordId']);

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

            // Must be logged in
            new Rule\IsLoggedIn(),

            // Must be staff
            new Rule\HasRole(Role::STAFF)
        );

        $this->addRoutePermission($authorizer,
            'getReportBuilder|getReport|getReportPrint|getInsert|postInsert|postComment|postCommentDelete', $permissionStaff);

        // Only staff, or parents of the student can view a students pastoral care record list page

        $permissionIndex = new Rule\All(

            // Must be logged in
            new Rule\IsLoggedIn(),

            // And either....
            new Rule\Any
            (
                // A staff member
                new Rule\HasRole(Role::STAFF),

                // Or a parent of a student
                new Rule\All
                (
                    new Rule\HasRole(Role::PARENT),
                    new IsParentOfStudent(),

                    // And parent access is allowed via config
                    new IsParentAccessAllowed()
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getIndex', $permissionIndex);

        // Only staff that are superusers, pastoral moderators (with write perms) or the owner of a record can update it
        $permissionUpdate = new Rule\All(

            // Must be logged in
            new Rule\IsLoggedIn(),

            // Must be a staff member
            new Rule\HasRole(Role::STAFF),

            // And either superuser, moderator or owner of record
            new Rule\Any
            (
                new Rule\IsSuperuser(),
                new IsPastoralModeratorWrite(),
                new IsOwner()
            )
        );

        $this->addRoutePermission($authorizer, 'getUpdate|postUpdate|postDelete', $permissionUpdate);

        // Only staff that are superusers, pastoral moderators (with write perms) or the owner of a record can update it
        $permissionDeleteComment = new Rule\All(

        // Must be logged in
            new Rule\IsLoggedIn(),

            // Must be a staff member
            new Rule\HasRole(Role::STAFF),

            // And either superuser, moderator or owner of record
            new Rule\Any
            (
                new Rule\IsSuperuser(),
                new IsPastoralModeratorWrite(),
                new IsCommentOwner()
            )
        );

        $this->addRoutePermission($authorizer, 'postCommentDelete', $permissionDeleteComment);
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
     * @return PastoralCommentMapper
     */
    public function getPastoralCommentMapper()
    {
        return $this->pastoralCommentMapper;
    }

    /**
     * @param PastoralCommentMapper $mapper
     * @return $this
     */
    public function setPastoralCommentMapper(PastoralCommentMapper $mapper)
    {
        $this->pastoralCommentMapper = $mapper;
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

    /** @var PastoralCommentMapper */
    protected $pastoralCommentMapper;
}

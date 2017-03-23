<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Pastoral\Authorizer\Rule\IsCommentOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsInAudience;
use Schoolbox\Pastoral\Authorizer\Rule\IsOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsParentAccessAllowed;
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
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Category\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'pastoral';

    /** @var ErrorMapper $errorMapper */
    protected $errorMapper;

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $routes = [
            'GET' => [
                ''                                                          => 'getIndex',
                '/groups'                                                   => 'getGroups',
                '/search'                                                   => 'getSearch',
                '/student/{studentId:\d+}'                                  => 'getStudent',
                '/student/{studentId:\d+}/record/{recordId:\d+}'            => 'getStudentRecord',
                '/student/{studentId:\d+}/record/insert'                    => 'getStudentRecordInsert',
                '/student/{studentId:\d+}/record/{recordId:\d+}/update'     => 'getStudentRecordUpdate',
            ],
            'POST' => [
                '/record/insert'                                            => 'postRecordInsert',
                '/record/update'                                            => 'postRecordUpdate',
                '/record/{recordId:\d+}/delete'                             => 'postRecordDelete',
                '/comment/insert'                                           => 'postCommentInsert',
                '/record/{recordId:\d+}/comment/{commentId:\d+}/delete'     => 'postCommentDelete',
            ]
        ];

        foreach ($routes as $httpMethod => $route) {
            foreach ($route as $path => $controllerMethod) {
                $this->register($httpMethod, $path, $controllerMethod);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'config'                    => $injector->get('config'),
            'errorMapper'               => ErrorMapper::getInstance(),
            'fileMapper'                => FileMapper::getInstance(),
            'pastoralMapper'            => PastoralMapper::getInstance(),
            'pastoralAccessMapper'      => PastoralAccessMapper::getInstance(),
            'pastoralAttachmentMapper'  => PastoralAttachmentMapper::getInstance(),
            'pastoralCategoryMapper'    => PastoralCategoryMapper::getInstance(),
            'pastoralCommentMapper'     => PastoralCommentMapper::getInstance(),
            'pastoralSeverityMapper'    => PastoralSeverityMapper::getInstance(),
            'pastoralTagMapper'         => PastoralTagMapper::getInstance(),
            'pastoralTypeMapper'        => PastoralTypeMapper::getInstance(),
            'pastoralUploadQueueMapper' => PastoralUploadQueueMapper::getInstance(),
            'roleMapper'                => RoleMapper::getInstance(),
            'userMapper'                => UserMapper::getInstance(),
            'userAccessMapper'          => UserAccessMapper::getInstance(),
        ];

        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class, $mappers));
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
        $this->addRoutePreload($matcher, '.*', function ($params) {

            $injectedValues = [];

            // Load student record

            if (array_key_exists('studentId', $params)) {
                try {
                    /** @var UserMapper $userMapper */
                    $userMapper = $this->getMapperFactory()->get(UserMapper::class);

                    $student = $userMapper->findWithSubjects($params['studentId']);
                    $student->loadTeachers();
                    $student->loadParents();

                    $injectedValues['student'] = $student;
                } catch (MapperException $e) {
                }
            }

            // Load comment

            if (array_key_exists('commentId', $params)) {
                try {
                    /** @var PastoralCommentMapper $pastoralCommentMapper */
                    $pastoralCommentMapper = $this->getMapperFactory()->get(PastoralCommentMapper::class);

                    $injectedValues['comment'] = $pastoralCommentMapper->findById($params['commentId']);
                } catch (MapperException $e) {
                }
            }

            // Load pastoral record and associated access details

            if (array_key_exists('recordId', $params)) {
                try {
                    /** @var PastoralMapper $pastoralMapper */
                    $pastoralMapper = $this->getMapperFactory()->get(PastoralMapper::class);

                    /** @var PastoralAccessMapper $pastoralAccessMapper */
                    $pastoralAccessMapper = $this->getMapperFactory()->get(PastoralAccessMapper::class);

                    $injectedValues['record'] = $pastoralMapper->findById($params['recordId']);
                    $injectedValues['access'] = $pastoralAccessMapper->findByColumn('pastoral_id', $params['recordId']);
                } catch (MapperException $e) {
                }
            }

            return $injectedValues;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getMapperFactory()->get(UserMapper::class);

        // In most cases pastoral moderators that are staff members get access to everything regardless of all other permissions.
        // The only exception is write permissions where the moderator is not a write level moderator

        $permissionPastoralModerator = new Rule\All(
            new IsPastoralModerator(),
            new Rule\HasRole(Role::STAFF)
        );

        // To access a students pastoral page you must be either
        //
        // - Any staff member, or
        // - Parent of child and have parent access in enabled in Schoolbox config

        $permissionViewStudent = new Rule\Any(
            $permissionPastoralModerator,
            new Rule\HasRole(Role::STAFF),
            new Rule\All
            (
                new Rule\HasRole(Role::PARENT),
                new IsParentAccessAllowed(),
                new Rule\IsParentOfUser($this->getExternalUser(), $userMapper, 'studentId')
            )
        );

        // To view a pastoral record you must be either
        //
        // - Staff member in the audience of the record, or
        // - Parent of child who is in the audience of the record and have parent access enabled in Schoolbox config

        $permissionViewRecord = new Rule\Any(
            $permissionPastoralModerator,
            new Rule\All(
                new Rule\HasRole(Role::STAFF),
                new IsInAudience()
            ),
            new Rule\All (
                new Rule\HasRole(Role::PARENT),
                new IsParentAccessAllowed(),
                new IsInAudience(),
                new Rule\IsParentOfUser($this->getExternalUser(), $userMapper, 'studentId')
            )
        );

        // In order to update a pastoral record you must be a staff member who is either
        //
        // - The author of the record, or
        // - A pastoral moderator with write level permissions

        $permissionRecordUpdate = new Rule\All(
            new Rule\HasRole(Role::STAFF),
            new Rule\Any (
                new IsPastoralModeratorWrite(),
                new IsOwner()
            )
        );

        // In order to delete a pastoral comment you must be a staff member who is either
        //
        // - The author of the comment, or
        // - A pastoral moderator with write level permissions

        $permissionCommentDelete = new Rule\All(
            new Rule\HasRole(Role::STAFF),
            new Rule\Any (
                new IsPastoralModeratorWrite(),
                new IsCommentOwner()
            )
        );

        // In order to insert a pastoral comment you must be a staff member who is either
        //
        // - The author of the record, or
        // - A pastoral moderator with write level permissions, or
        // - In the audience of the record

        $permissionCommentInsert = new Rule\All(
            new Rule\HasRole(Role::STAFF),
            new Rule\Any (
                new IsPastoralModeratorWrite(),
                new IsOwner(),
                new IsInAudience()
            )
        );

        // All staff (and only staff) can access these functions
        //
        // - Pastoral dashboard
        // - Pastoral search
        // - Add pastoral record
        // - Add pastoral comment

        $this->addRoutePermission($authorizer,
            'getIndex|getSearch|getStudentRecordInsert|postCommentInsert|postCommentInsert',
            new Rule\Any(
                $permissionPastoralModerator,
                new Rule\HasRole(Role::STAFF)
            )
        );

        // Only write level pastoral moderators can access the group management screen

        $this->addRoutePermission($authorizer, 'getGroups', new IsPastoralModeratorWrite());

        // These are all described above

        $this->addRoutePermission($authorizer, 'getStudent', $permissionViewStudent);
        $this->addRoutePermission($authorizer, 'getStudentRecord', $permissionViewRecord);
        $this->addRoutePermission($authorizer, 'getStudentRecordUpdate|postRecordUpdate|postRecordDelete', $permissionRecordUpdate);
        $this->addRoutePermission($authorizer, 'postCommentDelete', $permissionCommentDelete);
        $this->addRoutePermission($authorizer, 'postCommentInsert', $permissionCommentInsert);
    }
}

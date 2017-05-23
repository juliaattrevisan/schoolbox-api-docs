<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

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
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'pastoral';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        // Define permissions for the routes

        $permissionPastoralModerator = new Rule\All(new Rule\IsLoggedIn(), new Rule\HasRole(Role::STAFF), new IsPastoralModerator());
        $permissionPastoralModeratorWrite = new Rule\All(new Rule\IsLoggedIn(), new Rule\HasRole(Role::STAFF), new IsPastoralModeratorWrite());
        $permissionStaff = new Rule\All(new Rule\IsLoggedIn(), new Rule\HasRole(Role::STAFF));
        $permissionParentOfStudent = new Rule\All(new Rule\IsLoggedIn(), new IsParentAccessAllowed(), new Rule\HasRole(Role::PARENT), new Rule\IsParentOfUser($this->getExternalUser(), UserMapper::getInstance(), 'studentId'));

        // User is in the audience of the pastoral record

        $permissionInRecordAudience = new IsInAudience();

        // Permissions required to view a students pastoral page

        $permissionStudentView = new Rule\Any($permissionPastoralModerator, $permissionStaff, $permissionParentOfStudent);

        // Permissions required to view/update/delete a pastoral record (any staff member can insert)

        $permissionRecordOwner = new Rule\All(new IsOwner(), $permissionStaff);
        $permissionRecordView = new Rule\Any($permissionPastoralModerator, new Rule\All($permissionStaff, $permissionInRecordAudience), new Rule\All($permissionParentOfStudent, $permissionInRecordAudience));
        $permissionRecordUpdate = new Rule\All(new Rule\Any($permissionPastoralModeratorWrite, $permissionRecordOwner));
        $permissionRecordDelete = new Rule\All(new Rule\Any($permissionPastoralModeratorWrite, $permissionRecordOwner));

        // Permissions required to insert/delete a pastoral comment

        $permissionCommentOwner = new Rule\All(new IsCommentOwner(), $permissionStaff);
        $permissionCommentInsert = new Rule\All($permissionStaff, new Rule\Any($permissionPastoralModeratorWrite, $permissionInRecordAudience, $permissionRecordOwner));
        $permissionCommentDelete = new Rule\All($permissionStaff, new Rule\Any($permissionPastoralModeratorWrite, $permissionCommentOwner, $permissionRecordOwner));

        // Define routes and assign permissions to them

        $this->registerRoutesArray([
            'GET' => [
                ''                                                          => ['getIndex' => $permissionStaff],
                '/groups'                                                   => ['getGroups' => $permissionPastoralModerator],
                '/search'                                                   => ['getSearch' => $permissionStaff],
                '/student/{studentId:\d+}'                                  => ['getStudent' => $permissionStudentView],
                '/student/{studentId:\d+}/linked/{linkedStudentId:\d+}'     => ['getLinked' => $permissionStudentView],
                '/student/{studentId:\d+}/record/{recordId:\d+}'            => ['getStudentRecord' => $permissionRecordView],
                '/student/{studentId:\d+}/record/insert'                    => ['getStudentRecordInsertForm' => $permissionStaff],
                '/student/{studentId:\d+}/record/{recordId:\d+}/update'     => ['getStudentRecordUpdateForm' => $permissionRecordUpdate],
            ],
            'POST' => [
                '/record/insert'                                            => ['postRecordInsert' => $permissionStaff],
                '/record/{recordId:\d+}/update'                             => ['postRecordUpdate' => $permissionRecordUpdate],
                '/record/{recordId:\d+}/delete'                             => ['postRecordDelete' => $permissionRecordDelete],
                '/record/{recordId:\d+}/comment/insert'                     => ['postCommentInsert' => $permissionCommentInsert],
                '/record/{recordId:\d+}/comment/{commentId:\d+}/delete'     => ['postCommentDelete' => $permissionCommentDelete],
            ]
        ]);
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
            'pastoralGroupMapper'       => PastoralGroupMapper::getInstance(),
            'pastoralGroupMemberMapper' => PastoralGroupMemberMapper::getInstance(),
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
                    /** @var ErrorMapper $errorMapper */
                    $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
                    throw $errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }
            }

            // Load student record

            if (array_key_exists('linkedStudentId', $params)) {
                try {
                    if (!isset($userMapper)) {
                        /** @var UserMapper $userMapper */
                        $userMapper = $this->getMapperFactory()->get(UserMapper::class);
                    }

                    $student = $userMapper->findWithSubjects($params['linkedStudentId']);
                    $student->loadTeachers();
                    $student->loadParents();

                    $injectedValues['linkedStudent'] = $student;
                } catch (MapperException $e) {
                    /** @var ErrorMapper $errorMapper */
                    $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
                    throw $errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
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
                    /** @var ErrorMapper $errorMapper */
                    $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
                    throw $errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }
            }

            return $injectedValues;
        });
    }
}

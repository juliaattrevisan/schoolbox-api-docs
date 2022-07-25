<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachment;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Authorizer\Rule\IsCommentOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsInAudience;
use Schoolbox\Pastoral\Authorizer\Rule\IsOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsCommentAllowed;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;

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

        $permissionPastoralModeratorModerate = new HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL);
        $permissionPastoralModeratorWrite    = new HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_WRITE);
        $permissionPastoralModeratorRead     =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ),
            );

        $permissionStaff =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\HasRolePermission(Role::PERMISSION_ACCESS_PASTORAL, Role::STAFF),
                    new Rule\All(
                        new Rule\HasRole(Role::STAFF),
                        $permissionPastoralModeratorRead
                    )
                )
            );

        $userIdSource = static function (array $params) {
            if (isset($params['studentId'])) {
                return $params['studentId'];
            } elseif (isset($params['recordId'])) {
                try {
                    $record = PastoralMapper::getInstance()->findById($params['recordId']);
                    return $record->getStudent()->getId();
                } catch (MapperException $e) {
                }
            }
            return null;
        };

        $permissionParentOfStudent = new Rule\LazyCallback(
            function () use ($userIdSource) {
                return new Rule\All(
                    new Rule\IsLoggedIn(),
                    new Rule\HasRole(Role::PARENT),
                    new Rule\IsConfigSetTo(
                        ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED,
                        true,
                        $this->getConfig(),
                    ),
                    new Rule\IsParentOfUser(
                        $this->getExternalUser(),
                        UserMapper::getInstance(),
                        $userIdSource,
                    )
                );
            }
        );

        $permissionStudent = new Rule\LazyCallback(
            function () use ($userIdSource) {
                return new Rule\All(
                    new Rule\IsLoggedIn(),
                    new Rule\HasRole(Role::STUDENT),
                    new Rule\IsConfigSetTo(
                        ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED,
                        true,
                        $this->getConfig(),
                    ),
                    new Rule\IsUser($userIdSource),
                );
            }
        );

        // Permissions required to view a students pastoral page
        $permissionStudentView = new Rule\Any(
            $permissionPastoralModeratorRead,
            $permissionStaff,
            $permissionParentOfStudent,
            $permissionStudent,
        );

        // Permissions required to view/update/delete a pastoral record (any staff member can insert)
        $permissionRecordView = new Rule\Any(
            $permissionPastoralModeratorRead,
            new Rule\All(
                new Rule\Any(
                    $permissionStaff,
                    $permissionParentOfStudent,
                    $permissionStudent,
                ),
                new IsInAudience(),
            )
        );

        $permissionRecordUpdate = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                $permissionPastoralModeratorWrite,
                new Rule\All(
                    $permissionStaff,
                    new IsOwner(),
                )
            )
        );

        $permissionRecordDelete = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                $permissionPastoralModeratorModerate,
                new Rule\All(
                    $permissionStaff,
                    new IsOwner()
                )
            )
        );

        $permissionCommentInsert =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\All(
                        $permissionStaff,
                        new Rule\Any(
                            $permissionPastoralModeratorWrite,
                            new IsInAudience(),
                            new IsOwner(),
                        )
                    ),
                    new Rule\All(
                        new IsInAudience(),
                        new IsCommentAllowed(),
                    ),
                ),
            );

        $permissionCommentDelete = new Rule\All(
            $permissionStaff,
            new Rule\Any(
                $permissionPastoralModeratorWrite,
                new IsCommentOwner(),
                new IsOwner(),
            )
        );

        $permissionActionUpdate = new Rule\Any(
            $permissionPastoralModeratorRead,
            new Rule\All(
                $permissionStaff,
                new IsInAudience()
            ),
        );

        // Define routes and assign permissions to them

        $this->registerRoutesArray(
            [
                'GET'  => [
                    ''                                                                             => ['getIndex' => $permissionStaff],
                    '/groups'                                                                      => ['getGroups' => $permissionPastoralModeratorRead],
                    '/actions'                                                                     => ['getActions' => $permissionStaff],
                    '/search'                                                                      => ['getSearch' => $permissionStaff],
                    '/student/{studentId:\d+}'                                                     => ['getStudent' => $permissionStudentView],
                    '/students/count/{studentIds:[\d\x2c]+}'                                       => ['getStudentsCount' => $permissionStudentView],
                    '/student/{studentId:\d+}/record/{recordId:\d+}'                               => ['getStudentRecord' => $permissionRecordView],
                    '/student/{studentId:\d+}/linked/{linkedStudentId:\d+}'                        => ['getLinked' => $permissionStudentView],
                    '/student/{studentId:\d+}/record/{recordId:\d+}/attachment/{attachmentId:\d+}' => ['getStudentRecordAttachment' => $permissionRecordView],
                    '/student/{studentId:\d+}/record/insert'                                       => ['getStudentRecordInsertForm' => $permissionStaff],
                    '/student/{studentId:\d+}/record/{recordId:\d+}/update'                        => ['getStudentRecordUpdateForm' => $permissionRecordUpdate],
                ],
                'POST' => [
                    '/record/insert'                                        => ['postRecordInsert' => $permissionStaff],
                    '/record/{recordId:\d+}/update'                         => ['postRecordUpdate' => $permissionRecordUpdate],
                    '/record/{recordId:\d+}/delete'                         => ['postRecordDelete' => $permissionRecordDelete],
                    '/record/{recordId:\d+}/comment/insert'                 => ['postCommentInsert' => $permissionCommentInsert],
                    '/record/{recordId:\d+}/comment/{commentId:\d+}/delete' => ['postCommentDelete' => $permissionCommentDelete],
                    '/record/{recordId:\d+}/action/update'                  => ['postActionUpdate' => $permissionActionUpdate],
                ],
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('config'),
            $this->getMapperFactory()->get(FileMapper::class),
            $this->injector->get('pastoralDataSource'),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(PastoralAuditMapper::class),
            $this->getMapperFactory()->get(PastoralSubTypeMapper::class),
            $this->getMapperFactory()->get(PastoralTypeMapper::class),
            $this->getMapperFactory()->get(PastoralUploadQueueMapper::class),
            $this->getMapperFactory()->get(PastoralSeverityMapper::class),
            $this->injector->get('pastoralRevisionDataSource'),
            $this->getMapperFactory()->get(PastoralTagMapper::class),
            $this->getMapperFactory()->get(Action\Mapper::class),
            $this->getMapperFactory()->get(RecordAction\Mapper::class),
            $this->getMapperFactory()->get(PastoralCommentMapper::class),
            $this->getMapperFactory()->get(PastoralAttachmentMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(PastoralAccessMapper::class),
            $this->getMapperFactory()->get(PastoralGroupMapper::class),
            $this->getMapperFactory()->get(PastoralGroupMemberMapper::class),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(PastoralReportMapper::class),
            $this->getMapperFactory()->get(Audience\Mapper::class),
            $this->getMapperFactory()->get(TermMapper::class),
            new Comment\Translator\ModelToArray(
                new User\Translator\Author\ModelToArray(),
                $this->injector->get('router'),
                $this->injector->get('authorizer'),
                $this->injector->get('user'),
                new ScrubAdapter(),
            ),
            $this->injector->get('filesystem.storageProxy'),
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
        $this->addRoutePreload($matcher, '.*', function ($params) {
            $injectedValues = [];

            // Load student record

            if (array_key_exists('studentId', $params) && $params['studentId'] !== '0') {
                try {
                    /** @var UserMapper $userMapper */
                    $userMapper = $this->getMapperFactory()->get(UserMapper::class);

                    $student = $userMapper->findWithSubjects($params['studentId']);
                    $student->loadTeachers();
                    $student->loadParents();

                    $injectedValues['student'] = $student;
                } catch (MapperException $e) {
                    throw $this->generateError(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }
            }

            // Load linked student details

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
                    throw $this->generateError(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }
            }

            // Load comment

            if (array_key_exists('commentId', $params)) {
                try {
                    /** @var PastoralCommentMapper $pastoralCommentMapper */
                    $pastoralCommentMapper = $this->getMapperFactory()->get(PastoralCommentMapper::class);

                    $injectedValues['comment'] = $pastoralCommentMapper->findById($params['commentId']);
                } catch (MapperException $e) {
                    throw $this->generateError(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }
            }

            // Load pastoral record and associated access details

            if (array_key_exists('recordId', $params)) {
                try {
                    // TODO make current user easily available in path matcher
                    /** @var DataSource $pastoralDataSource */
                    $pastoralDataSource = $this->injector->get('pastoralDataSource');

                    /** @var User $user */
                    $user = $this->injector->get('user');

                    $record                      = $pastoralDataSource->findById($user, $params['recordId']);
                    $injectedValues['record']    = $record;
                    $injectedValues['access']    = $record->getAccess();
                    $injectedValues['studentId'] = $record->getStudent()->getId();
                } catch (MapperException $e) {
                    throw $this->generateError(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }

                // Load attachment (if requested) out of item
                // Pastoral data source has already filtered out attachments I can't view

                if (array_key_exists('attachmentId', $params)) {
                    $attachment = $injectedValues['record']->getAttachments()->getBy('id', $params['attachmentId']);
                    if ($attachment instanceof PastoralAttachment) {
                        $injectedValues['attachment'] = $attachment;
                    } else {
                        throw $this->generateError(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                    }
                }
            }

            /**
             * load in several students
             */
            if (array_key_exists('studentIds', $params)) {
                $studentIds = array_unique(explode(',', $params['studentIds']));

                $students = User::createCollection();

                /** @var UserMapper $userMapper */
                $userMapper = $this->getMapperFactory()->get(UserMapper::class);

                try {
                    foreach ($studentIds as $studentId) {
                        $student = $userMapper->findWithSubjects($studentId);
                        $student->loadTeachers();
                        $student->loadParents();

                        $students->add($student);
                    }
                } catch (MapperException $e) {
                    throw $this->generateError(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }

                if (count($students) === 0) {
                    throw $this->generateError(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }

                $injectedValues['students'] = $students;
            }

            return $injectedValues;
        });
    }

    /**
     * Helper for generating a human-readable exception.
     *
     * @param int $errorCode A `\Schoolbox\Error\Mapper::ERROR_*` constant.
     * @return SchoolboxException
     */
    private function generateError($errorCode)
    {
        /** @var ErrorMapper $errorMapper */
        $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
        return $errorMapper->generateException($errorCode);
    }
}

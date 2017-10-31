<?php
/**
 * Controller for pastoral care
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use External_Pastoral;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperDeletedException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverityModel;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\SubType\Model as PastoralSubTypeModel;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTagsModel;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Search\Results;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /** @var IConfigLoader $config */
    protected $config;

    /** @var ErrorMapper $errorMapper */
    protected $errorMapper;

    /** @var FileMapper $fileMapper */
    protected $fileMapper;

    /** @var PastoralMapper $pastoralMapper */
    protected $pastoralMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    protected $pastoralGroupMapper;

    /** @var PastoralGroupMemberMapper $pastoralGroupMemberMapper */
    protected $pastoralGroupMemberMapper;

    /** @var PastoralAttachmentMapper $pastoralAttachmentMapper */
    protected $pastoralAttachmentMapper;

    /** @var PastoralAuditMapper $pastoralAuditMapper */
    protected $pastoralAuditMapper;

    /** @var PastoralSubTypeMapper $pastoralSubTypeMapper */
    protected $pastoralSubTypeMapper;

    /** @var PastoralCommentMapper $pastoralCommentMapper */
    protected $pastoralCommentMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    protected $pastoralSeverityMapper;

    /** @var PastoralRevisionMapper $pastoralRevisionMapper */
    protected $pastoralRevisionMapper;

    /** @var PastoralTagMapper $pastoralTagMapper */
    protected $pastoralTagMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    protected $pastoralTypeMapper;

    /** @var PastoralUploadQueueMapper $pastoralUploadQueueMapper */
    protected $pastoralUploadQueueMapper;

    /** @var RoleMapper $roleMapper */
    protected $roleMapper;

    /** @var UserMapper $userMapper */
    protected $userMapper;

    /** @var UserAccessMapper $userAccessMapper */
    protected $userAccessMapper;

    /** @var PastoralReportMapper $pastoralReportMapper */
    private $pastoralReportMapper;

    const ATTACHZONE_PRIVATE = 'private';
    const ATTACHZONE_PUBLIC  = 'public';

    /**
     * Constructor
     *
     * @param IConfigLoader             $config
     * @param FileMapper                $fileMapper
     * @param Mapper                    $pastoralMapper
     * @param UserAccessMapper          $userAccessMapper
     * @param PastoralAuditMapper       $pastoralAuditMapper
     * @param PastoralSubTypeMapper     $pastoralSubTypeMapper
     * @param PastoralTypeMapper        $pastoralTypeMapper
     * @param PastoralUploadQueueMapper $pastoralUploadQueueMapper
     * @param PastoralSeverityMapper    $pastoralSeverityMapper
     * @param PastoralRevisionMapper    $pastoralRevisionMapper
     * @param PastoralTagMapper         $pastoralTagMapper
     * @param PastoralCommentMapper     $pastoralCommentMapper
     * @param PastoralGroupMapper       $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param PastoralAttachmentMapper  $pastoralAttachmentMapper
     * @param RoleMapper                $roleMapper
     * @param UserMapper                $userMapper
     * @param PastoralAccessMapper      $pastoralAccessMapper
     * @param ErrorMapper               $errorMapper
     * @param PastoralReportMapper      $pastoralReportMapper
     */
    public function __construct(
        IConfigLoader $config,
        FileMapper $fileMapper,
        PastoralMapper $pastoralMapper,
        UserAccessMapper $userAccessMapper,
        PastoralAuditMapper $pastoralAuditMapper,
        PastoralSubTypeMapper $pastoralSubTypeMapper,
        PastoralTypeMapper $pastoralTypeMapper,
        PastoralUploadQueueMapper $pastoralUploadQueueMapper,
        PastoralSeverityMapper $pastoralSeverityMapper,
        PastoralRevisionMapper $pastoralRevisionMapper,
        PastoralTagMapper $pastoralTagMapper,
        PastoralCommentMapper $pastoralCommentMapper,
        PastoralAttachmentMapper $pastoralAttachmentMapper,
        RoleMapper $roleMapper,
        UserMapper $userMapper,
        PastoralAccessMapper $pastoralAccessMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        ErrorMapper $errorMapper,
        PastoralReportMapper $pastoralReportMapper
    ) {
        $this->config                    = $config;
        $this->errorMapper               = $errorMapper;
        $this->fileMapper                = $fileMapper;
        $this->pastoralAuditMapper       = $pastoralAuditMapper;
        $this->pastoralAccessMapper      = $pastoralAccessMapper;
        $this->pastoralGroupMapper       = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper = $pastoralGroupMemberMapper;
        $this->pastoralAttachmentMapper  = $pastoralAttachmentMapper;
        $this->pastoralSubTypeMapper     = $pastoralSubTypeMapper;
        $this->pastoralCommentMapper     = $pastoralCommentMapper;
        $this->pastoralMapper            = $pastoralMapper;
        $this->pastoralSeverityMapper    = $pastoralSeverityMapper;
        $this->pastoralRevisionMapper    = $pastoralRevisionMapper;
        $this->pastoralTagMapper         = $pastoralTagMapper;
        $this->pastoralTypeMapper        = $pastoralTypeMapper;
        $this->pastoralUploadQueueMapper = $pastoralUploadQueueMapper;
        $this->roleMapper                = $roleMapper;
        $this->userMapper                = $userMapper;
        $this->userAccessMapper          = $userAccessMapper;
        $this->pastoralReportMapper      = $pastoralReportMapper;
    }

    /**
     * Display pastoral dashboard page
     */
    public function getIndex()
    {
        $this->getResponse()->content->set([
            'template' => '@pastoral/index.twig',
            'data'     => [
                'user'             => $this->getUser(),
                'recentStudents'   => $this->pastoralAuditMapper->findRecentStudents(),
                'recentDashboard'  => $this->pastoralMapper->findRecentDashboard($this->getUser()),
                'reportsDashboard' => $this->pastoralReportMapper->findForUserDashboard($this->getUser()),
                'groups'           => $this->pastoralGroupMemberMapper->findByColumn(
                    'user_id',
                    $this->getUser()->getId()
                ),
            ],
        ]);
    }

    /**
     * Display pastoral care search results
     */
    public function getSearch()
    {
        $get = $this->getRequest()->query;

        $this->getResponse()->content->set([
            'template' => '@pastoral/search.twig',
            'data'     => [
                'search'              => trim($get['keyword']),
                'results'             => $this->pastoralMapper->getSearchResults($get['keyword'], $this->getUser()),
                'students'            => $this->userMapper->findStudents($get['keyword']),
                'minimumSearchLength' => Results::MIN_SEARCH_LENGTH,
            ],
        ]);
    }

    /**
     * Display an individual students pastoral records
     *
     * @param User $student
     *
     * @throws MapperException
     */
    public function getStudent($student)
    {
        $get = $this->getRequest()->query;

        // Create an audit record of anyone viewing a student record

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        if (!is_numeric($get['type'])) {
            // Retrieve all of the students pastoral records

            $records = $this->pastoralMapper->findByColumn('student_id', $student->getId(), [
                'order' => [
                    'created_at' => 'desc',
                ],
            ]);
        } else {
            // Retrieve results filtered by pastoral type

            $records = $this->pastoralMapper->findByColumnList([
                'student_id' => $student->getId(),
                'type_id'    => $get['type'],
            ], [
                'order' => [
                    'created_at' => 'desc',
                ],
            ]);
        }

        $records = $this->pastoralMapper->filterRecordsForUser($records, $this->getUser());

        // Limit the number of pastoral records to display on each page of pastoral records

        $resultsPerPage = $this->config->get('pastoral_page_size');

        // Set the current page number

        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? $get['pageCurrent'] : 1;

        if ($pageCurrent > ceil($records->count() / $resultsPerPage)) {
            $pageCurrent = ceil($records->count() / $resultsPerPage);
        } else {
            if ($pageCurrent < 1) {
                $pageCurrent = 1;
            }
        }

        $this->getResponse()->content->set([
            'template' => '@pastoral/student.twig',
            'data'     => [
                'user'           => $this->getUser(),
                'recordCount'    => $records->count(),
                'records'        => $records->slice(($pageCurrent - 1) * $resultsPerPage, $resultsPerPage),
                'student'        => $student,
                'pageCurrent'    => $pageCurrent,
                'types'          => $this->pastoralTypeMapper->findAll(),
                'linkedStudents' => $this->pastoralMapper->getLinkedStudents($student),
                'typeSelected'   => $get['type'],
            ],
        ]);
    }

    /**
     * Display linked records between two students
     *
     * @param User $student
     * @param User $linkedStudent
     *
     * @throws SchoolboxException
     */
    public function getLinked($student, $linkedStudent)
    {
        $get = $this->getRequest()->query;

        // Create an audit record of anyone viewing a student record

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($linkedStudent);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        // Retrieve all of the students pastoral records

        $records = $this->pastoralMapper->filterRecordsForUser(
            $this->pastoralMapper->findLinked($student, $linkedStudent, $get['type']),
            $this->getUser()
        );

        // Limit the number of pastoral records to display on each page of pastoral records

        $resultsPerPage = $this->config->get('pastoral_page_size');

        // Set the current page number

        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? $get['pageCurrent'] : 1;

        if ($pageCurrent > ceil($records->count() / $resultsPerPage)) {
            $pageCurrent = ceil($records->count() / $resultsPerPage);
        } else {
            if ($pageCurrent < 1) {
                $pageCurrent = 1;
            }
        }

        $this->getResponse()->content->set([
            'template' => '@pastoral/linked.twig',
            'data'     => [
                'user'           => $this->getUser(),
                'recordCount'    => $records->count(),
                'records'        => $records->slice(($pageCurrent - 1) * $resultsPerPage, $resultsPerPage),
                'student'        => $student,
                'linkedStudent'  => $linkedStudent,
                'pageCurrent'    => $pageCurrent,
                'types'          => $this->pastoralTypeMapper->findAll(),
                'linkedStudents' => $this->pastoralMapper->getLinkedStudents($student),
                'typeSelected'   => $get['type'],
            ],
        ]);
    }

    /**
     * Display a single pastoral care record
     *
     * @param Model $record
     * @param User  $student
     *
     * @throws SchoolboxException
     */
    public function getStudentRecord($record, $student)
    {
        // They've manually put an ID number they don't have access to- so the record doesn't get hydrated in preload

        if (!$record instanceof Model) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        $record = $this->pastoralMapper->filterRecordForUser($record, $this->getUser());

        // Create an audit record

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setPastoral($record);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        // Moderators can wee revision history

        $moderator = $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;

        if ($moderator) {
            $record->setRevisions(
                $this->pastoralRevisionMapper->findByColumn('root_id', $record->getId(), [
                    'order' => [
                        'updated_at' => 'desc',
                    ],
                ])
            );
        }

        // Hydrate group members so we can display them in the visibility section

        foreach ($record->getAccess() as $access) {
            if ($access->getGroup() instanceof PastoralGroup) {
                try {
                    $access->setGroup(
                        $this->pastoralGroupMapper->findById(
                            $access->getGroup()->getId()
                        )
                    );
                } catch (MapperException $e) {
                    // Noop- group no longer exists
                }
            }
        }

        $commentsPublic  = $record->getComments()->filterBy('private', false);
        $commentsPrivate = $record->getComments()->filterBy('private', true);

        // Lets render this thing

        $this->getResponse()->content->set([
            'template' => '@pastoral/record.twig',
            'data'     => [
                'record'          => $record,
                'commentsPublic'  => $commentsPublic,
                'commentsPrivate' => $commentsPrivate,
                'moderator'       => $moderator,
                'user'            => $this->getUser(),
                'showSeverity'    => $this->getConfigLoader()->get('pastoral_severity'),
            ],
        ]);
    }

    /**
     * Insert pastoral care record
     *
     * @param User $student
     *
     * @throws SchoolboxException
     */
    public function getStudentRecordInsertForm($student)
    {
        // Throw in a blank pastoral record with the student populated

        /** @var PastoralModel $record */
        $record = $this->pastoralMapper->createModel();
        $record->setStudent($student);

        $this->getStudentRecordUpdateForm($record);
    }

    /**
     * Update pastoral care record
     *
     * @param Model $record
     *
     * @throws SchoolboxException
     */
    public function getStudentRecordUpdateForm($record)
    {
        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            $severities = $this->pastoralSeverityMapper->findAll(['order' => ['order' => 'ASC']]);
        }

        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@pastoral/modify.twig',
            'data'     => [
                'record'            => $record,
                'allowIndividual'   => $this->config->get('pastoral_individual_access'),
                'allowParents'      => $this->config->get('pastoral_parent_access'),
                'types'             => $this->pastoralTypeMapper->findAll(['order' => ['name' => 'ASC']]),
                'categories'        => $this->pastoralSubTypeMapper->findAll(['order' => ['name' => 'ASC']]),
                'severities'        => $severities,
                'roles'             => $this->roleMapper->findAll(['order' => ['name' => 'ASC']]),
                'groups'            => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
                'attachzonePublic'  => $this->getAttachZone(self::ATTACHZONE_PUBLIC, $record),
                'attachzonePrivate' => $this->getAttachZone(self::ATTACHZONE_PRIVATE, $record),
            ],
        ]);
    }

    /**
     * Delete a comment from pastoral record
     *
     * @param PastoralComment $comment
     *
     * @throws SchoolboxException
     */
    public function postCommentDelete($comment)
    {
        // Delete from internal database

        $this->pastoralCommentMapper->delete($comment);

        // Delete from external database

        $pastoralExternal = new External_Pastoral();
        $pastoralExternal->deleteComment($comment);
    }

    /**
     * Add a comment to a pastoral record
     *
     * @throws SchoolboxException
     * @throws RouteNotFound
     */
    public function postCommentInsert()
    {
        $post = $this->getRequest()->post;

        try {
            /** @var Model $record */
            $record = $this->pastoralMapper->findById($post['pastoral_id']);

            if (!empty(trim($post["comment_body"]))) {
                // Insert the comment to the internal database

                /** @var PastoralComment $comment */
                $comment = $this->pastoralCommentMapper->createModel();

                $comment->setPastoral($record);
                $comment->setBody(trim($post["comment_body"]));
                $comment->setOwner($this->getUser());
                $comment->setPrivate($post["comment_private"]);
                $this->pastoralCommentMapper->save($comment);

                // Insert the comment to the external database

                $pastoralExternal = new External_Pastoral();
                $pastoralExternal->createComment($comment);
            }

            // Go back to pastoral record

            $this->getResponse()->redirect->afterPost(
                $this->getrouter()->generate('pastoral.getStudentRecord', [
                    'studentId' => $post['student_id'],
                    'recordId'  => $record->getId(),
                ])
            );
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }
    }

    /**
     * Assign tags to a record, spawning them if required, or resurrecting them
     * if these tags once existed
     *
     * @param Model  $record
     * @param string $tagCsv
     */
    private function assignTagsToRecord(Model $record, $tagCsv)
    {
        $tags = explode(",", $tagCsv);

        foreach ($tags as $tagCurrent) {
            if (trim($tagCurrent) != '') {
                try {
                    /* Assign an existing tag to this pastoral record
                     */

                    /** @var PastoralTagsModel $tag */
                    $tag = $this->pastoralTagMapper->findOneByColumn('name', $tagCurrent, [], ['cache' => false]);
                } catch (MapperDeletedException $e) {
                    /* Restore this tag to active duty
                     */
                    $tag = $e->getDeletedObject();

                    $tag->setDeletedAt(null);
                    $this->pastoralTagMapper->save($tag);
                } catch (MapperException $e) {
                    /* Tag doesn't exist - spawn it, save it, then assign it
                     */
                    $tag = new PastoralTagsModel();
                    $tag->setName($tagCurrent);
                    $this->pastoralTagMapper->save($tag);
                }

                $this->pastoralTagMapper->assignToRecord($record, $tag);
            }
        }
    }

    /**
     * Insert a pastoral care record
     *
     * @throws SchoolboxException
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function postRecordInsert()
    {
        $post = $this->getRequest()->post;

        // External interface

        $pastoralExternal = new External_Pastoral();

        // Keep track of IDs created

        /** @var Collection|Model $records */
        $records = new Collection();

        // Make an array of all students this record needs to be created on

        $students = [];

        // The student the user was currently viewing

        $students[$post["student_id"]] = 1;

        // And any other students specified on the pastoral record

        if (is_array($post["duplicate_id"])) {
            foreach ($post["duplicate_id"] as $studentId) {
                if (trim($studentId) != '') {
                    $students[$studentId] = 1;
                }
            }
        }

        // Go through every kid

        foreach ($students as $student_id => $value) {
            $record = $this->pastoralMapper->createModel();

            // If this is not the first record, set the parent ID

            if (isset($parent)) {
                $record->setParent($parent);
            }

            try {
                /** @var User $student */
                $student = $this->userMapper->findById($student_id);
            } catch (MapperException $e) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
            }

            try {
                /** @var PastoralTypeModel $type */
                $type = $this->pastoralTypeMapper->findById($post["typeId"]);

                try {
                    /** @var PastoralSubTypeModel $subtype */
                    $subtype = $this->pastoralSubTypeMapper->findById($post["subtypeId"]);
                    $record->setSubType($subtype);
                } catch (MapperException $e) {
                    // Noop, this can be blank
                }

                if (!$this->getConfigLoader()->get('pastoral_severity')) {
                    $severity = null;
                } else {
                    /** @var PastoralSeverityModel $severity */
                    $severity = $this->pastoralSeverityMapper->findById($post["severityId"]);
                }
            } catch (MapperException $e) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }

            $record->setModifiedBy($this->getUser());
            $record->setStudent($student);
            $record->setHeading(trim($post["heading"]));
            $record->setType($type);
            $record->setPoints($post["points"]);
            $record->setSeverity($severity);
            $record->setBodyPublic(trim($post["body_public"]));
            $record->setBodyPrivate(trim($post["body_private"]));
            $record->setOwner($this->getUser());
            $this->pastoralMapper->setSessionData($_SESSION);
            $this->pastoralMapper->save($record);

            $records->add($record);

            // Store the first records ID for use as parent for subsequent copies

            if (!isset($parent)) {
                $parent = $record;
            }

            $this->assignTagsToRecord($record, $post["tags"]);

            // Save system role permissions

            foreach ($post["role"] as $role_id => $permission) {
                if ($permission != PastoralAccessMapper::ACCESS_NONE) {
                    try {
                        /** @var Role $role */
                        $role = $this->roleMapper->findById($role_id);
                    } catch (MapperException $e) {
                        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                    }

                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setPastoral($record);
                    $access->setPrivate($permission == PastoralAccessMapper::ACCESS_PRIVATE);
                    $access->setRole($role);
                    $this->pastoralAccessMapper->save($access);
                }
            }

            // Save pastoral group permissions

            foreach ($post["group"] as $group_id => $permission) {
                if ($permission != PastoralAccessMapper::ACCESS_NONE) {
                    try {
                        /** @var PastoralGroup $group */
                        $group = $this->pastoralGroupMapper->findById($group_id);
                    } catch (MapperException $e) {
                        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                    }

                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setPastoral($record);
                    $access->setPrivate($permission == PastoralAccessMapper::ACCESS_PRIVATE);
                    $access->setGroup($group);
                    $this->pastoralAccessMapper->save($access);
                }
            }

            // Save individual user permissions

            foreach ($post["user"] as $user_id => $permission) {
                if ($permission != PastoralAccessMapper::ACCESS_NONE) {
                    try {
                        /** @var UserMapper $user */
                        $user = $this->userMapper->findById($user_id);
                    } catch (MapperException $e) {
                        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                    }

                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setPastoral($record);
                    $access->setPrivate($permission == PastoralAccessMapper::ACCESS_PRIVATE);
                    $access->setUser($user);
                    $this->pastoralAccessMapper->save($access);
                }
            }

            // Save record in external database, and set pastoral external_id in Schoolbox database

            $pastoralExternal->syncPastoral($record);
        }

        $collectorFactory = new CollectorFactory();

        /** @var Model $record */
        foreach ($records as $record) {
            // Save attachments

            if (is_array($post['attachment'])) {
                $attachmentObjects = $collectorFactory->build($post['attachment'])->getAll();

                /** @var Storage $storageObject */
                foreach ($attachmentObjects as $storageObject) {
                    // Only add attachments which don't already exist

                    if ($this->config->get("external_pastoral_upload")) {
                        // Queue file for upload to Synergetic DocMan
                        $queue = $this->pastoralUploadQueueMapper->createModel();
                        $queue->setPastoral($record);
                        $queue->setExternalId($this->getUser()->getExternalId());
                        $queue->setStudentExternalId($record->getStudent()->getExternalId());
                        $queue->setStorage($storageObject);
                        $queue->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PENDING);
                        $this->pastoralUploadQueueMapper->save($queue);
                    }

                    $attachment = $this->pastoralAttachmentMapper->createModel();
                    $attachment->setPastoral($record);
                    $attachment->setStorage($storageObject);
                    $this->pastoralAttachmentMapper->save($attachment);
                }
            }
        }

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoral.getStudent', [
                'studentId' => $post["student_id"],
            ])
        );
    }

    /**
     * Update a pastoral care record
     *
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function postRecordUpdate()
    {
        $post = $this->getRequest()->post;

        /** @var Model $record */
        $record = $this->pastoralMapper->findByIdWithFallback($post['id']);

        // External interface

        $pastoralExternal = new External_Pastoral();

        // Keep track of IDs created

        /** @var Collection|Model $records */
        $records = new Collection();

        $records->add($record);

        try {
            if ($this->getRequest()->post["typeId"] != null) {
                /** @var PastoralTypeModel $type */
                $type = $this->pastoralTypeMapper->findById($this->getRequest()->post["typeId"]);
            } else {
                $type = null;
            }
        } catch (MapperException $e) {
            // Can't trust imported data from Synergetic
            $type = null;
        }

        try {
            if ($this->getRequest()->post["subtypeId"] != null) {
                /** @var PastoralSubTypeModel $subtype */
                $subtype = $this->pastoralSubTypeMapper->findById($this->getRequest()->post["subtypeId"]);
            } else {
                $subtype = null;
            }
        } catch (MapperException $e) {
            // Can't trust imported data from Synergetic
            $subtype = null;
        }

        try {
            if ($this->getRequest()->post["severityId"] != null) {
                /** @var PastoralSeverityModel $severity */
                $severity = $this->pastoralSeverityMapper->findById($this->getRequest()->post["severityId"]);
            } else {
                $severity = null;
            }
        } catch (MapperException $e) {
            // Can't trust imported data from Synergetic
            $severity = null;
        }

        $record->setModifiedBy($this->getUser());
        $record->setHeading(trim($post["heading"]));
        $record->setType($type);
        $record->setSubType($subtype);
        $record->setPoints($post["points"]);
        $record->setSeverity($severity);
        $record->setBodyPublic(trim($post["body_public"]));
        $record->setBodyPrivate(trim($post["body_private"]));
        $this->pastoralMapper->setSessionData($_SESSION);
        $this->pastoralMapper->save($record);

        // Delete old tags

        $this->pastoralTagMapper->deleteForRecord($record);

        // Save new tags

        $this->assignTagsToRecord($record, $post["tags"]);

        // Delete olds user permissions

        $this->pastoralAccessMapper->deleteByColumnList(['pastoral_id' => $record->getId()]);

        // Save new system role permissions

        foreach ($post["role"] as $role_id => $permission) {
            if ($permission != PastoralAccessMapper::ACCESS_NONE) {
                try {
                    /** @var Role $role */
                    $role = $this->roleMapper->findById($role_id);
                } catch (MapperException $e) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }

                $access = $this->pastoralAccessMapper->createModel();
                $access->setPastoral($record);
                $access->setPrivate($permission == PastoralAccessMapper::ACCESS_PRIVATE);
                $access->setRole($role);
                $this->pastoralAccessMapper->save($access);
            }
        }

        // Save new pastoral group permissions

        foreach ($post["group"] as $group_id => $permission) {
            if ($permission != PastoralAccessMapper::ACCESS_NONE) {
                try {
                    /** @var PastoralGroup $group */
                    $group = $this->pastoralGroupMapper->findById($group_id);
                } catch (MapperException $e) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }

                $access = $this->pastoralAccessMapper->createModel();
                $access->setPastoral($record);
                $access->setPrivate($permission == PastoralAccessMapper::ACCESS_PRIVATE);
                $access->setGroup($group);
                $this->pastoralAccessMapper->save($access);
            }
        }

        // Save new individual user permissions

        foreach ($post["user"] as $user_id => $permission) {
            if ($permission != PastoralAccessMapper::ACCESS_NONE) {
                try {
                    /** @var UserMapper $user */
                    $user = $this->userMapper->findById($user_id);
                } catch (MapperException $e) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }

                $access = $this->pastoralAccessMapper->createModel();
                $access->setPastoral($record);
                $access->setPrivate($permission == PastoralAccessMapper::ACCESS_PRIVATE);
                $access->setUser($user);
                $this->pastoralAccessMapper->save($access);
            }
        }

        // Update external record

        $pastoralExternal->syncPastoral($record);

        $collectorFactory = new CollectorFactory();

        // Attachments

        /** @var Model $record */
        foreach ($records as $record) {
            // Need to do both public and private attachments

            $types = [self::ATTACHZONE_PRIVATE, self::ATTACHZONE_PUBLIC];

            foreach ($types as $type) {
                // Get a boolean true/false to indicate if its public or private

                $isPrivate = $type == self::ATTACHZONE_PRIVATE ? 1 : 0;

                // First thing to do is find out if we need to keep all of the existing attachments on the record

                $retainedAttachments = [];

                // Load all of the existing attachments

                try {
                    $attachments = $this->pastoralAttachmentMapper->findByColumnList([
                        'pastoral_id' => $record->getId(),
                        'private'     => $isPrivate,
                    ]);
                } catch (MapperException $e) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }

                // Iterate and check if all of these attachments were passed through with the form data

                /** @var PastoralAttachmentModel $attachment */
                foreach ($attachments as $attachment) {
                    $foundAttachment = false;

                    if (!empty($post["{$type}_attachments"])) {
                        // Annoyingly if there is one file, this is a string- otherwise it is an array

                        if (!is_array($post["{$type}_attachments"])) {
                            $post["{$type}_attachments"] = [$post["{$type}_attachments"]];
                        }

                        $attachmentObjects = $collectorFactory->build($post["{$type}_attachments"])->getAll();

                        /** @var Storage $storageObject */
                        foreach ($attachmentObjects as $storageObject) {
                            if ($attachment->getStorage()->getHash() == $storageObject->getHash()) {
                                // Found the attachment in both database and form data- we need to keep it

                                $retainedAttachments[] = $attachment->getStorage()->getHash();
                                $foundAttachment       = true;
                                break;
                            }
                        }
                    }

                    // If the attachment was not in the form data, remove it from the database

                    if (!$foundAttachment) {
                        $this->pastoralAttachmentMapper->delete($attachment);
                    }
                }

                // Save any new attachments

                if (isset($post["{$type}_attachments"])) {
                    // Annoyingly if there is one file, this is a string- otherwise it is an array

                    if (!is_array($post["{$type}_attachments"])) {
                        $post["{$type}_attachments"] = [$post["{$type}_attachments"]];
                    }

                    $attachmentObjects = $collectorFactory->build($post["{$type}_attachments"])->getAll();

                    foreach ($attachmentObjects as $storageObject) {
                        // Only add attachments which don't already exist on the record

                        if (!in_array($storageObject->getHash(), $retainedAttachments)) {
                            $this->attachmentSave($record, $storageObject, (bool)$isPrivate);
                        }
                    }
                }
            }
        }

        // Delete any unassociated tags everywhere

        $this->pastoralTagMapper->purgeUnassociated();

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoral.getStudent', [
                'studentId' => $post["student_id"],
            ])
        );
    }

    /**
     * Save pastoral attachements
     *
     * @param PastoralModel $record
     * @param IStorage      $storageObject
     * @param bool          $private
     */
    private function attachmentSave($record, $storageObject, $private)
    {
        // Only add attachments which don't already exist on the record

        // Check if external SIS uploads are enabled

        if ($this->config->get("external_pastoral_upload")) {
            // Queue file for upload to external SIS

            $queue = $this->pastoralUploadQueueMapper->createModel();
            $queue->setPastoral($record);
            $queue->setExternalId($this->getUser()->getExternalId());
            $queue->setStudentExternalId($record->getStudent()->getExternalId());
            $queue->setStorage($storageObject);
            $queue->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PENDING);
            $this->pastoralUploadQueueMapper->save($queue);
        }

        $attachment = $this->pastoralAttachmentMapper->createModel();
        $attachment->setPastoral($record);
        $attachment->setStorage($storageObject);
        $attachment->setPrivate($private);
        $this->pastoralAttachmentMapper->save($attachment);
    }

    /**
     * Delete a pastoral care record
     *
     * @param PastoralModel $record
     *
     * @throws RouteNotFound
     */
    public function postRecordDelete($record)
    {
        $studentId = $record->getStudent()->getId();

        /* Normally we would apply this logic in the mapper, but in this case we intentionally
         * do so in the Controller as here we are acting on a single record.
         * The specific risk is that during import, the system first deletes all
         * schoolbox records that map to a SIS pastoral record. If this logic
         * is on the mapper, it catastrophical deletes the records in both systems.
         * Having the logic here also aligns with the approach taken for create/update SB => SIS.
         */
        if ($this->pastoralMapper->delete($record)) {
            // Delete record from external database
            $pastoralExternal = new External_Pastoral();
            $pastoralExternal->deletePastoral($record);
        }

        $this->getResponse()->redirect->afterPost(
            $this->getrouter()->generate('pastoral.getStudent', [
                'studentId' => $studentId,
            ])
        );
    }

    /**
     * Insert an attach zone into the template
     *
     * @param string        $name   Name of attachzone on page
     * @param PastoralModel $record Pastoral record from which to load existing attachments
     *
     * @return array
     */
    private function getAttachZone($name, $record = null)
    {
        $zone = new Zone($name);
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select attachment')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload attachment')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setFileVar("{$name}_attachments")
            ->setMulti(true);

        // Attach any existing files

        if ($record instanceof PastoralModel) {
            if ($record->getAttachments() instanceof Collection) {
                /** @var PastoralAttachmentModel $attachment */
                foreach ($record->getAttachments() as $attachment) {
                    if ($attachment->getPrivate() && $name == self::ATTACHZONE_PRIVATE) {
                        $zone->attachFiles([$attachment->getStorage()->getHash()]);
                    } elseif (!$attachment->getPrivate() && $name == self::ATTACHZONE_PUBLIC) {
                        $zone->attachFiles([$attachment->getStorage()->getHash()]);
                    }
                }
            }
        }

        return $zone->getTwigContext();
    }

    /**
     * Download an attachment from file system, permissions are applied via routing system
     *
     * @param PastoralAttachmentModel $attachment
     */
    public function getStudentRecordAttachment($attachment)
    {
        if (!$attachment->getStorage()->getMimetype()->getInline()) {
            $attach = "attachment;";
        } else {
            $attach = "";
        }

        // Send headers

        if ($attachment->getStorage()->getMime()) {
            header("Content-Type: {$attachment->getStorage()->getMime()}");
        } else {
            header("Content-Type: application/octet-stream");
        }

        $basename = $attachment->getStorage()->getBasename();

        header("Pragma: ");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: $attach filename=\"$basename\"");
        header("Content-Length: " . $attachment->getStorage()->getSize());
        fpassthru($attachment->getStorage()->readStream());

        exit;
    }
}

/*
 * This code self-destructed about a year ago...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/

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
use External_Pastoral;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception\Deleted as MapperDeletedException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Audit\Model as PastoralAudit;
use Schoolbox\Pastoral\Group\Model as Group;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\Category\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\Category\Model as PastoralCategoryModel;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverityModel;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTagsModel;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Search\Results;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;

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

    /** @var PastoralCategoryMapper $pastoralCategoryMapper */
    protected $pastoralCategoryMapper;

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

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param FileMapper $fileMapper
     * @param Mapper $pastoralMapper
     * @param UserAccessMapper $userAccessMapper
     * @param PastoralAuditMapper $pastoralAuditMapper
     * @param PastoralCategoryMapper $pastoralCategoryMapper
     * @param PastoralTypeMapper $pastoralTypeMapper
     * @param PastoralUploadQueueMapper $pastoralUploadQueueMapper
     * @param PastoralSeverityMapper $pastoralSeverityMapper
     * @param PastoralRevisionMapper $pastoralRevisionMapper
     * @param PastoralTagMapper $pastoralTagMapper
     * @param PastoralCommentMapper $pastoralCommentMapper
     * @param PastoralGroupMapper $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param PastoralAttachmentMapper $pastoralAttachmentMapper
     * @param RoleMapper $roleMapper
     * @param UserMapper $userMapper
     * @param PastoralAccessMapper $pastoralAccessMapper
     * @param ErrorMapper $errorMapper
     */
    public function __construct(
        IConfigLoader $config,
        FileMapper $fileMapper,
        PastoralMapper $pastoralMapper,
        UserAccessMapper $userAccessMapper,
        PastoralAuditMapper $pastoralAuditMapper,
        PastoralCategoryMapper $pastoralCategoryMapper,
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
        ErrorMapper $errorMapper
    ) {
        $this->config = $config;
        $this->errorMapper = $errorMapper;
        $this->fileMapper = $fileMapper;
        $this->pastoralAuditMapper = $pastoralAuditMapper;
        $this->pastoralAccessMapper = $pastoralAccessMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper = $pastoralGroupMemberMapper;
        $this->pastoralAttachmentMapper = $pastoralAttachmentMapper;
        $this->pastoralCategoryMapper = $pastoralCategoryMapper;
        $this->pastoralCommentMapper = $pastoralCommentMapper;
        $this->pastoralMapper = $pastoralMapper;
        $this->pastoralSeverityMapper = $pastoralSeverityMapper;
        $this->pastoralRevisionMapper = $pastoralRevisionMapper;
        $this->pastoralTagMapper = $pastoralTagMapper;
        $this->pastoralTypeMapper = $pastoralTypeMapper;
        $this->pastoralUploadQueueMapper = $pastoralUploadQueueMapper;
        $this->roleMapper = $roleMapper;
        $this->userMapper = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * Display pastoral dashboard page
     */
    public function getIndex()
    {
        $this->getResponse()->content->set([
            'template' => '@pastoral/index.twig',
            'data' => [
                'user' => $this->getUser(),
                'recentStudents'    => $this->pastoralAuditMapper->findRecentStudents(),
                'recentDashboard'   => $this->pastoralMapper->findRecentDashboard(),
                'groups'            => $this->pastoralGroupMemberMapper->findByColumn('user_id', $this->getUser()->getId())
            ]
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
            'data' => [
                'search'                => trim($get['keyword']),
                'results'               => $this->pastoralMapper->getSearchResults($get['keyword']),
                'students'              => $this->userMapper->findStudents($get['keyword']),
                'minimumSearchLength'   => Results::MIN_SEARCH_LENGTH
            ]
        ]);
    }

    /**
     * Display an individual students pastoral records
     *
     * @param User $student
     * @throws SchoolboxException
     */
    public function getStudent($student)
    {
        $get = $this->getRequest()->query;

        // Create an audit record of anyone viewing a student record

        /** @var PastoralAudit $auditRecord */
        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        if (!is_numeric($get['type'])) {

            // Retrieve all of the students pastoral records

            $records = $this->pastoralMapper->findByColumn('student_id', $student->getId());

        } else {

            // Retrieve results filtered by pastoral type

            $records = $this->pastoralMapper->findByColumnList([
                'student_id' => $student->getId(),
                'type_id' => $get['type']
            ]);
        }

        // Limit the number of pastoral records to display on each page of pastoral records

        $resultsPerPage = $this->config->get('pastoral_page_size');

        // Set the current page number

        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? $get['pageCurrent'] : 1;

        $this->getResponse()->content->set([
            'template' => '@pastoral/student.twig',
            'data' => [
                'records'           => $records,
                'student'           => $student,
                'pageCurrent'       => $pageCurrent,
                'resultsPerPage'    => $resultsPerPage,
                'types'             => $this->pastoralTypeMapper->findAll(),
                'linkedStudents'    => $this->pastoralMapper->getLinkedStudents($student),
                'typeSelected'      => $get['type']
            ]
        ]);
    }

    /**
     * Display a single pastoral care record
     *
     * @param Model $record
     * @param User $student
     * @throws SchoolboxException
     */
    public function getStudentRecord($record, $student)
    {
        // Create an audit record

        /** @var PastoralAudit $auditRecord */
        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setPastoral($record);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        // Load the details of any related records (I would love to find a way to do this in the mapper,
        // however recursion is a thing)...

        $relatedRecords = new Collection();

        if ($record->getParent() instanceof Model) {
            $relatedRecords = $relatedRecords
                ->merge($this->pastoralMapper->findByColumn('parent_id', $record->getParent()->getId()))
                ->add($this->pastoralMapper->findById($record->getParent()->getId()));
        } else {
            $relatedRecords = $relatedRecords
                ->merge($this->pastoralMapper->findByColumn('parent_id', $record->getId()))
                ->add($record);
        }

        $record->setRelatedRecords($relatedRecords);

        // Moderators can wee revision history

        $moderator = $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL);

        if ($moderator->count() > 0) {
            $record->setRevisions(
                $this->pastoralRevisionMapper->findByColumn('root_id', $record->getId())
            );
        }

        // Lets render this thing

        $this->getResponse()->content->set([
            'template' => '@pastoral/record.twig',
            'data' => [
                'record' => $record,
                'moderator' => $moderator,
                'user' => $this->getUser(),
                'showSeverity' => $this->getConfigLoader()->get('pastoral_severity')
            ]
        ]);
    }

    /**
     * Insert pastoral care record
     *
     * @param User $student
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
            'data' => [
                'record' => $record,
                'allowIndividual' => $this->config->get('pastoral_individual_access'),
                'allowParents' => $this->config->get('pastoral_parent_access'),
                'types' => $this->pastoralTypeMapper->findAll(['order' => ['name' => 'ASC']]),
                'categories' => $this->pastoralCategoryMapper->findAll(['order' => ['name' => 'ASC']]),
                'severities' => $severities,
                'roles' => $this->roleMapper->findAll(['order' => ['name' => 'ASC']]),
                'groups' => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
                'attachzone' => $this->getAttachZone($record)
            ]
        ]);
    }

    /**
     * Delete a comment from pastoral record
     *
     * @param PastoralComment $comment
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
     */
    public function postCommentInsert()
    {
        $post = $this->getRequest()->post;

        try {
            /** @var Model $record */
            $record = $this->pastoralMapper->findById($post['pastoral_id']);

            if (!empty(trim($post["body"]))) {

                // Insert the comment to the internal database

                /** @var PastoralComment $comment */
                $comment = $this->pastoralCommentMapper->createModel();

                $comment->setPastoral($record);
                $comment->setBody($post["body"]);
                $comment->setOwner($this->getUser());
                $this->pastoralCommentMapper->save($comment);

                // Insert the comment to the external database

                $pastoralExternal = new External_Pastoral();
                $pastoralExternal->createComment($comment);
            }

            // Go back to pastoral record

            $this->getResponse()->redirect->afterPost(
                $this->getrouter()->generate('pastoral.getStudentRecord', [
                    'studentId' => $post['student_id'],
                    'recordId' => $record->getId()
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
     * @param Model $record
     * @param $tagCsv
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
                }
                catch (MapperDeletedException $e) {

                    /* Restore this tag to active duty
                     */
                    $tag = $e->getDeletedObject();

                    $tag->setDeletedAt(null);
                    $this->pastoralTagMapper->save($tag);
                }
                catch (MapperException $e) {

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
     * @throws SchoolboxException
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

        $students = array();

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

            /** @var Model $record */
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
                    /** @var PastoralCategoryModel $category */
                    $category = $this->pastoralCategoryMapper->findById($post["categoryId"]);
                    $record->setCategory($category);
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
            $record->setHeading($post["heading"]);
            $record->setType($type);
            $record->setPoints($post["points"]);
            $record->setSeverity($severity);
            $record->setBody($post["body"]);
            $record->setPrivate($post["private"]);
            $record->setOwner($this->getUser());
            $this->pastoralMapper->setSessionData($_SESSION);
            $this->pastoralMapper->save($record);

            $records->add($record);

            // Store the first records ID for use as parent for subsequent copies

            if (!isset($parent)) {
                $parent = $record;
            }

            // Save the authorised users

            if (isset($post["user_id"])) {
                if (is_array($post["user_id"])) {
                    foreach ($post["user_id"] as $user_id) {

                        try {
                            $user = $this->userMapper->findById($user_id);
                        } catch (MapperException $e) {
                            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                        }

                        $access = $this->pastoralAccessMapper->createModel();
                        $access->setPastoral($record);
                        $access->setUser($user);
                        $this->pastoralAccessMapper->save($access);
                    }
                }
            }

            // Save the tags

            $this->assignTagsToRecord($record, $post["tags"]);

            // Save the audiences

            foreach ($post["role"] as $role_id => $checked) {
                if ($checked) {
                    try {
                        /** @var Role $role */
                        $role = $this->roleMapper->findById($role_id);
                    } catch (MapperException $e) {
                        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                    }

                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setPastoral($record);
                    $access->setRole($role);
                    $this->pastoralAccessMapper->save($access);
                }
            }

            if (is_array($post["group"])) {
                foreach ($post["group"] as $group_id => $checked) {
                    if ($checked) {
                        try {
                            /** @var Group $group */
                            $group = $this->pastoralGroupMapper->findById($group_id);
                        } catch (MapperException $e) {
                            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                        }

                        $access = $this->pastoralAccessMapper->createModel();
                        $access->setPastoral($record);
                        $access->setGroup($group);
                        $this->pastoralAccessMapper->save($access);
                    }
                }
            }

            // Save record in external database, and set pastoral external_id in Schoolbox database

            $pastoralExternal->createPastoral($record);
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
                'studentId' => $post["student_id"]
            ])
        );
    }

    /**
     * Update a pastoral care record
     * @throws SchoolboxException
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
            if ($this->getRequest()->post["categoryId"] != null) {
                /** @var PastoralCategoryModel $category */
                $category = $this->pastoralCategoryMapper->findById($this->getRequest()->post["categoryId"]);
            } else {
                $category = null;
            }
        } catch (MapperException $e) {
            // Can't trust imported data from Synergetic
            $category = null;
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
        $record->setHeading($post["heading"]);
        $record->setType($type);
        $record->setCategory($category);
        $record->setPoints($post["points"]);
        $record->setSeverity($severity);
        $record->setBody($post["body"]);
        $record->setPrivate($post["private"]);
        $this->pastoralMapper->setSessionData($_SESSION);
        $this->pastoralMapper->save($record);

        // Delete old tags
        $this->pastoralTagMapper->deleteForRecord($record);

        // Save the tags

        $this->assignTagsToRecord($record, $post["tags"]);

        // Delete user permissions

        $accessRecords = $record->getAccess();

        /* Delete all the individual access instances for this pastoral record
         */
        $deleteIds = trim($post['deleteIds']);
        $individualAccessIds = [];

        if (strlen($deleteIds)) {
            $individualAccessIds = array_unique(explode(',', $deleteIds));
        }

        foreach ($individualAccessIds as $currentAccessId) {
            $this->pastoralAccessMapper->deleteByColumnList([
                'pastoral_id' => $record->getId(),
                'user_id'     => $currentAccessId
            ]);
        }

        // Update the authorised users

        if (isset($post["user_id"])) {
            if (is_array($post["user_id"])) {
                foreach ($post["user_id"] as $user_id) {

                    $found = false;

                    // Don't re-add deleted users

                    if (count($individualAccessIds)) {

                        /** @var PastoralAccessModel $access */
                        foreach ($accessRecords as $access) {
                            if ($access->getUser()->getId() == $user_id) {
                                if (in_array($user_id, $individualAccessIds)) {
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }

                    $existingAccess = $this->pastoralAccessMapper->findByColumn('pastoral_id', $record->getId());

                    if (!$found) {

                        // Check not already have access

                        /** @var PastoralAccessModel $accessCurrent */
                        foreach ($existingAccess as $accessCurrent) {
                            if ($accessCurrent->getUser()->getId() == $user_id) {
                                $found = true;
                                break;
                            }
                        }
                    }

                    if (!$found) {
                        try {
                            $user = $this->userMapper->findById($user_id);
                        } catch (MapperException $e) {
                            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                        }

                        $access = $this->pastoralAccessMapper->createModel();
                        $access->setPastoral($record);
                        $access->setUser($user);
                        $this->pastoralAccessMapper->save($access);
                    }
                }
            }
        }

        // Update the role based audience

        foreach ($post["role"] as $role_id => $checked) {

            if ($checked) {

                // Add this access if it doesn't already exist

                $found = false;

                /** @var PastoralAccessModel $accessRecord */
                foreach ($accessRecords as $accessRecord) {
                    if ($accessRecord->getRole() instanceof Role) {
                        if ($accessRecord->getRole()->getId() == $role_id) {
                            $found = true;
                            break;
                        }
                    }
                }

                try {
                    /** @var Role $role */
                    $role = $this->roleMapper->findById($role_id);
                } catch (MapperException $e) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }

                if (!$found) {
                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setPastoral($record);
                    $access->setRole($role);
                    $this->pastoralAccessMapper->save($access);
                }
            } else {

                // Delete this access if it exists because its no longer ticked

                $this->pastoralAccessMapper->deleteByColumnList([
                    'pastoral_id' => $record->getId(),
                    'role_id' => $role_id
                ]);
            }
        }

        // Update group based permissions

        foreach ($post["group"] as $group_id => $checked) {

            if ($checked) {

                // Add this access if it doesn't already exist

                $found = false;

                /** @var PastoralAccessModel $accessRecord */
                foreach ($accessRecords as $accessRecord) {
                    if ($accessRecord->getGroup() instanceof Group) {
                        if ($accessRecord->getGroup()->getId() == $group_id) {
                            $found = true;
                            break;
                        }
                    }
                }

                try {
                    /** @var Group $group */
                    $group = $this->pastoralGroupMapper->findById($group_id);
                } catch (MapperException $e) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }

                if (!$found) {
                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setPastoral($record);
                    $access->setGroup($group);
                    $this->pastoralAccessMapper->save($access);
                }

            } else {

                // Delete this access if it exists because its no longer ticked

                $this->pastoralAccessMapper->deleteByColumnList([
                    'pastoral_id' => $record->getId(),
                    'group_id' => $group_id
                ]);
            }
        }        

        // Update external record

        if ($record->getExternalId() != "") {
            $pastoralExternal->updatePastoral($record);
        } else {
            $pastoralExternal->createPastoral($record);
        }

        $collectorFactory = new CollectorFactory();

        /** @var Model $record */
        foreach ($records as $record) {
            // Delete attachments if required

            // Keep track of attachments that aren't deleted so we don't add them again

            $retainedAttachments = array();

            // Get all attachments associated with this record

            try {
                $attachments = $this->pastoralAttachmentMapper->findByColumn('pastoral_id', $record->getId());
            } catch (MapperException $e) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }

            /** @var PastoralAttachmentModel $attachment */
            foreach ($attachments as $attachment) {

                $foundAttachment = false;

                if (is_array($post['attachment'])) {
                    $attachmentObjects = $collectorFactory->build($post['attachment'])->getAll();

                    /** @var Storage $storageObject */
                    foreach ($attachmentObjects as $storageObject) {

                        if ($attachment->getStorage()->getHash() == $storageObject->getHash()) {
                            $foundAttachment = true;
                            break;
                        }
                    }
                }

                if (!$foundAttachment) {

                    // The attachment was found in the database, remove it

                    $this->pastoralAttachmentMapper->delete($attachment);

                } else {

                    // We are keeping this attachment

                    $retainedAttachments[] = $attachment->getStorage()->getHash();
                }
            }

            // Save any new attachments

            if (is_array($post['attachment'])) {
                $attachmentObjects = $collectorFactory->build($post['attachment'])->getAll();

                foreach ($attachmentObjects as $storageObject) {

                    // Only add attachments which don't already exist on the record

                    if (!in_array($storageObject->getHash(), $retainedAttachments)) {
                        $this->attachmentSave($record, $storageObject);
                    }
                }
            }
        }

        // Delete any unassociated tags everywhere

        $this->pastoralTagMapper->purgeUnassociated();

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoral.getStudent', [
                'studentId' => $post["student_id"]
            ])
        );
    }

    /**
     * Save pastoral attachements
     *
     * @var PastoralModel $record
     * @var IStorage $storageObject
     */
    private function attachmentSave($record, $storageObject) {

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
        $this->pastoralAttachmentMapper->save($attachment);
    }

    /**
     * Delete a pastoral care record
     *
     * @param PastoralModel $record
     * @throws SchoolboxException
     */
    public function postRecordDelete($record)
    {
        $studentId = $record->getStudent()->getId();
        $this->pastoralMapper->delete($record);

        $this->getResponse()->redirect->afterPost(
            $this->getrouter()->generate('pastoral.getStudent', [
                'studentId' => $studentId
            ])
        );
    }

    /**
     * Insert an attach zone into the template
     *
     * @param PastoralModel $record Pastoral record from which to load existing attachments
     * @return array
     */
    private function getAttachZone($record = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select document')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload document')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(true);

        // Attach any existing files

        if ($record instanceof PastoralModel) {
            if ($record->getAttachments() instanceof Collection) {
                /** @var PastoralAttachmentModel $attachment */
                foreach ($record->getAttachments() as $attachment) {
                    $zone->attachFiles(array($attachment->getStorage()->getHash()));
                }
            }
        }

        return $zone->getTwigContext();
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

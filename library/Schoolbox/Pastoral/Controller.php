<?php
/**
 * Controller for pastoral care
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Aura\Web\Request\Values as RequestValues;
use Exception;
use External_Pastoral;
use PDOException;
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
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Revision\DataSource as PastoralRevisionDataSource;
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
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

class Controller extends Base
{
    /** @var IConfigLoader $config */
    protected $config;

    /** @var ErrorMapper $errorMapper */
    protected $errorMapper;

    /** @var FileMapper $fileMapper */
    protected $fileMapper;

    /** @var PastoralDataSource $pastoralDataSource */
    protected $pastoralDataSource;

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

    /** @var PastoralRevisionDataSource $pastoralRevisionDataSource */
    protected $pastoralRevisionDataSource;

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

    /** @var Audience\Mapper */
    private $audienceMapper;

    const ATTACHZONE_PRIVATE = 'private';
    const ATTACHZONE_PUBLIC  = 'public';

    /**
     * Constructor
     *
     * @param IConfigLoader              $config
     * @param FileMapper                 $fileMapper
     * @param PastoralDataSource         $pastoralDataSource
     * @param UserAccessMapper           $userAccessMapper
     * @param PastoralAuditMapper        $pastoralAuditMapper
     * @param PastoralSubTypeMapper      $pastoralSubTypeMapper
     * @param PastoralTypeMapper         $pastoralTypeMapper
     * @param PastoralUploadQueueMapper  $pastoralUploadQueueMapper
     * @param PastoralSeverityMapper     $pastoralSeverityMapper
     * @param PastoralRevisionDataSource $pastoralRevisionDataSource
     * @param PastoralTagMapper          $pastoralTagMapper
     * @param PastoralCommentMapper      $pastoralCommentMapper
     * @param PastoralGroupMapper        $pastoralGroupMapper
     * @param PastoralGroupMemberMapper  $pastoralGroupMemberMapper
     * @param PastoralAttachmentMapper   $pastoralAttachmentMapper
     * @param RoleMapper                 $roleMapper
     * @param UserMapper                 $userMapper
     * @param PastoralAccessMapper       $pastoralAccessMapper
     * @param ErrorMapper                $errorMapper
     * @param PastoralReportMapper       $pastoralReportMapper
     * @param Audience\Mapper            $audienceMapper;
     */
    public function __construct(
        IConfigLoader $config,
        FileMapper $fileMapper,
        PastoralDataSource $pastoralDataSource,
        UserAccessMapper $userAccessMapper,
        PastoralAuditMapper $pastoralAuditMapper,
        PastoralSubTypeMapper $pastoralSubTypeMapper,
        PastoralTypeMapper $pastoralTypeMapper,
        PastoralUploadQueueMapper $pastoralUploadQueueMapper,
        PastoralSeverityMapper $pastoralSeverityMapper,
        PastoralRevisionDataSource $pastoralRevisionDataSource,
        PastoralTagMapper $pastoralTagMapper,
        PastoralCommentMapper $pastoralCommentMapper,
        PastoralAttachmentMapper $pastoralAttachmentMapper,
        RoleMapper $roleMapper,
        UserMapper $userMapper,
        PastoralAccessMapper $pastoralAccessMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        ErrorMapper $errorMapper,
        PastoralReportMapper $pastoralReportMapper,
        Audience\Mapper $audienceMapper
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
        $this->pastoralDataSource        = $pastoralDataSource;
        $this->pastoralSeverityMapper    = $pastoralSeverityMapper;
        $this->pastoralRevisionDataSource    = $pastoralRevisionDataSource;
        $this->pastoralTagMapper         = $pastoralTagMapper;
        $this->pastoralTypeMapper        = $pastoralTypeMapper;
        $this->pastoralUploadQueueMapper = $pastoralUploadQueueMapper;
        $this->roleMapper                = $roleMapper;
        $this->userMapper                = $userMapper;
        $this->userAccessMapper          = $userAccessMapper;
        $this->pastoralReportMapper      = $pastoralReportMapper;
        $this->audienceMapper            = $audienceMapper;
    }


    /**
     * Display pastoral dashboard page
     *
     * @throws MapperException
     */
    public function getIndex(): void
    {
        $user              = $this->getUser();
        $groupsMemberships = $this->pastoralGroupMemberMapper->findByColumn('user_id', $user->getId());
        $groupsMemberships->sortByCallback(function (PastoralGroupMember $a, PastoralGroupMember $b) {
            return strnatcasecmp($a->getGroup()->getName(), $b->getGroup()->getName());
        });

        $this->getResponse()->content->set([
            'template' => '@pastoral/index.twig',
            'data'     => [
                'user'             => $user,
                'recentStudents'   => $this->pastoralAuditMapper->findRecentlyViewedStudentsForUser($user),
                'recentDashboard'  => $this->pastoralDataSource->findRecentDashboard($user),
                'reportsDashboard' => $this->pastoralReportMapper->findForUserDashboard($user),
                'groups'           => $groupsMemberships,
            ],
            'title' => 'Dashboard',
        ]);
    }

    /**
     * Display pastoral care search results
     *
     * @throws MapperException
     */
    public function getSearch(): void
    {
        $get = $this->getRequest()->query;

        $this->getResponse()->content->set([
            'template' => '@pastoral/search.twig',
            'data'     => [
                'search'              => trim($get['keyword']),
                'results'             => $this->pastoralDataSource->getSearchResults($this->getUser(), $get['keyword']),
                'students'            => $this->userMapper->findStudents($get['keyword']),
                'minimumSearchLength' => Results::MIN_SEARCH_LENGTH,
            ],
            'title' => 'Search',
        ]);
    }

    /**
     * Display an individual students pastoral records
     *
     * @param User $student
     *
     * @throws MapperException
     */
    public function getStudent($student): void
    {
        $get = $this->getRequest()->query;

        // Create an audit record of anyone viewing a student record
        // Hydrate user with group membership
        $user              = $this->getUser();

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setUser($user);
        $this->pastoralAuditMapper->save($auditRecord);

        $typeId = is_numeric($get['type']) ? (int)$get['type'] : null;

        // Limit the number of pastoral records to display on each page of pastoral records
        $resultsPerPage = (int)$this->config->get('pastoral_page_size');

        // Set the current page number
        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? (int)$get['pageCurrent'] : 1;

        $recordCount = $this->pastoralDataSource->countRecords($user, $student->getId(), $typeId);

        $maxPage = (int)ceil($recordCount / $resultsPerPage);
        if ($maxPage < 1) {
            $maxPage = 1;
        }

        if ($pageCurrent > $maxPage) {
            $pageCurrent = $maxPage;
        } elseif ($pageCurrent < 1) {
            $pageCurrent = 1;
        }
        // populate related records
        $pageRecords = $this->pastoralDataSource->findRecords($user, $student->getId(), $typeId, ($pageCurrent - 1) * $resultsPerPage, $resultsPerPage, null);

        $this->getResponse()->content->set([
            'template' => '@pastoral/student.twig',
            'data'     => [
                'user'           => $user,
                'recordCount'    => $recordCount,
                'records'        => $pageRecords,
                'student'        => $student,
                'pageCurrent'    => $pageCurrent,
                'types'          => $this->pastoralTypeMapper->findAll(),
                // TODO: This count is causing a performance issue SA-9641
                // 'linkedStudents' => $this->pastoralDataSource->getLinkedStudentRecordCount($user, $student->getId()),
                'linkedStudents' => [],
                'typeSelected'   => $typeId,
            ],
            'title'    => $student->getFullName(),
        ]);
    }

    /**
     * Display linked records between two students
     *
     * @param User $student
     * @param User $linkedStudent
     * @throws MapperException
     */
    public function getLinked($student, $linkedStudent): void
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

        $typeId = is_numeric($get['type']) ? (int)$get['type'] : null;

        // Limit the number of pastoral records to display on each page of pastoral records
        $resultsPerPage = (int)$this->config->get('pastoral_page_size');

        // Set the current page number
        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? (int)$get['pageCurrent'] : 1;

        // Retrieve all of the students pastoral records
        $recordCount = 0;
        $linkedCounts = $this->pastoralDataSource->getLinkedStudentRecordCount($this->getUser(), $student->getId());
        foreach ($linkedCounts as $linkedCount) {
            if ((int)$linkedCount['student_id'] === $linkedStudent->getId()) {
                $recordCount = (int)$linkedCount['count'];
                break;
            }
        }

        $maxPage = (int)ceil($recordCount / $resultsPerPage);
        if ($maxPage < 1) {
            $maxPage = 1;
        }

        if ($pageCurrent > $maxPage) {
            $pageCurrent = $maxPage;
        } elseif ($pageCurrent < 1) {
            $pageCurrent = 1;
        }

        // populate related records
        $pageRecords = $this->pastoralDataSource->findRecords($this->getUser(), $student->getId(), $typeId, ($pageCurrent - 1) * $resultsPerPage, $resultsPerPage, $linkedStudent->getId());

        $this->getResponse()->content->set([
            'template' => '@pastoral/student_linked.twig',
            'data'     => [
                'user'           => $this->getUser(),
                'recordCount'    => $recordCount,
                'records'        => $pageRecords,
                'student'        => $student,
                'linkedStudent'  => $linkedStudent,
                'pageCurrent'    => $pageCurrent,
                'types'          => $this->pastoralTypeMapper->findAll(),
                'linkedStudents' => $linkedCounts,
                'typeSelected'   => $get['type'],
            ],
            'title' => 'Linked Records',
        ]);
    }

    /**
     * Display a single pastoral care record
     *
     * @api /pastoral/student/{studentId:\d+}/record/{recordId:\d+}
     * @param Model $record
     * @param User  $student
     *
     * @throws MapperException
     * @throws Exception
     */
    public function getStudentRecord($record, $student): void
    {
        // They've manually put an ID number they don't have access to- so the record doesn't get hydrated in preload

        if (!$record instanceof Model) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        // Create an audit record

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setPastoral($record);
        $auditRecord->setUser($this->getUser());
        $this->pastoralAuditMapper->save($auditRecord);

        // Moderators can wee revision history

        $moderator = $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;

        if ($moderator) {
            $record->setRevisions($this->pastoralRevisionDataSource->findForRecord($this->getUser(), $record));
        }

        // Hydrate group members and their roles so we can display them in the visibility section
        if ($record->getAccess()) {
            foreach ($record->getAccess() as $access) {
                if ($access->getGroup() instanceof PastoralGroup) {
                    $this->pastoralGroupMapper->findById($access->getGroup()->getId());
                    foreach ($access->getGroup()->getMembers() as $member) {
                        $this->userMapper->findById($member->getUser()->getId());
                    }
                }
            }
        }

        $commentsPublic     = $record->getComments()->filterBy('private', false);
        $commentsPrivate    = $record->getComments()->filterBy('private', true);

        $attachmentsPublic  = $record->getAttachments()->filterBy('private', false);
        $attachmentsPrivate = $record->getAttachments()->filterBy('private', true);

        $access = [];

        $accessLevel       = (new AccessLevelCensor())->getAccessLevel($record, $this->getUser());
        $access['public']  = $accessLevel > PastoralAccessMapper::ACCESS_DENY;
        $access['private'] = $accessLevel > PastoralAccessMapper::ACCESS_PUBLIC;

        // Lets render this thing

        $this->getResponse()->content->set([
            'template' => '@pastoral/record.twig',
            'data'     => [
                'record'             => $record,
                'attachmentsPublic'  => $attachmentsPublic,
                'attachmentsPrivate' => $attachmentsPrivate,
                'commentsPublic'     => $commentsPublic,
                'commentsPrivate'    => $commentsPrivate,
                'moderator'          => $moderator,
                'user'               => $this->getUser(),
                'showSeverity'       => $this->getConfigLoader()->get('pastoral_severity'),
                'access'             => $access,
            ],
            'title' => $record->getHeading(),
        ]);
    }

    /**
     * Insert pastoral care record
     *
     * @param User $student
     * @throws MapperException
     */
    public function getStudentRecordInsertForm($student): void
    {
        // Throw in a blank pastoral record with the student populated

        /** @var PastoralModel $record */
        $record = $this->pastoralDataSource->createModel();
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
    public function getStudentRecordUpdateForm($record): void
    {
        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            $severities = $this->pastoralSeverityMapper->findAll(['order' => ['order' => 'ASC']]);
        }

        // Check if user is a pastoral moderator

        $moderator = $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;

        $allowParents = $this->config->get('pastoral_parent_access');

        // Only want staff/parent roles

        $roles = new Collection();
        $roleUsers = [];

        $rolesAll = $this->roleMapper->findAll(['order' => ['name' => 'ASC']]);

        /** @var Role $roleCurrent */
        foreach ($rolesAll as $roleCurrent) {
            // Staff role? Show all users
            if ($roleCurrent->isStaff()) {
                $roles->add($roleCurrent);
                $roleUsers[$roleCurrent->getId()] = $this->userMapper->countByColumn('group_id', $roleCurrent->getId());
            }

            // Parent role? Show only the # of this student's parents in this role
            if ($allowParents && $roleCurrent->isParent()) {
                $roles->add($roleCurrent);
                $roleUsers[$roleCurrent->getId()] = 0;
                foreach ($record->getStudent()->getParents() as $parent) {
                    if ($parent->getRole()->getId() == $roleCurrent->getId()) {
                        $roleUsers[$roleCurrent->getId()]++;
                    }
                }
            }
        }

        $audienceDefaults = $this->audienceMapper->findAll();

        // Render this thing out

        $this->getResponse()->content->set([
            'template' => '@pastoral/modify.twig',
            'data'     => [
                'record'            => $record,
                'canCreate'         => $this->getConfigLoader()->get('pastoral_create_tag_enable'),
                'allowIndividual'   => $this->config->get('pastoral_individual_access'),
                'allowParents'      => $allowParents,
                'types'             => $this->pastoralTypeMapper->findAll(['order' => ['name' => 'ASC']]),
                'subTypes'          => $this->pastoralSubTypeMapper->findAll(['order' => ['name' => 'ASC']]),
                'severities'        => $severities,
                'roles'             => $roles,
                'roleUsers'         => $roleUsers,
                'groups'            => $this->pastoralGroupMapper->findAll(['name' => ['order' => 'ASC']]),
                'moderator'         => $moderator,
                'attachzonePublic'  => $this->getAttachZone(self::ATTACHZONE_PUBLIC, $record),
                'attachzonePrivate' => $this->getAttachZone(self::ATTACHZONE_PRIVATE, $record),
                'audiences'         => $audienceDefaults,
            ],
            'title' => 'Pastoral Record',
        ]);
    }

    /**
     * Delete a comment from pastoral record
     *
     * @param PastoralComment $comment
     * @throws MapperException
     */
    public function postCommentDelete($comment): void
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
     * @throws Exception
     */
    public function postCommentInsert(): void
    {
        $post = $this->getRequest()->post;

        try {
            /** @var Model $record */
            $record = $this->pastoralDataSource->findById($this->getUser(), $post['pastoral_id']);

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
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function assignTagsToRecord(Model $record, $tagCsv): void
    {
        $tags = explode(",", $tagCsv);

        $canCreate = (bool)$this->getConfigLoader()->get('pastoral_create_tag_enable');

        foreach ($tags as $tagCurrent) {
            if (trim($tagCurrent) != '') {
                try {
                    /* Assign an existing tag to this pastoral record
                     */

                    /** @var PastoralTagsModel $tag */
                    $tag = $this->pastoralTagMapper->findOneByColumn('name', $tagCurrent, ['cache' => false]);
                } catch (MapperDeletedException $e) {

                    /* Restore this tag to active duty SA-2737
                     * This was put here to reduce dupe tags from original implementation
                     */

                } catch (MapperException $e) {
                    /* Tag doesn't exist - spawn it, save it, then assign it
                     */
                    if ($canCreate) {
                        $tag = new PastoralTagsModel();
                        $tag->setName($tagCurrent);
                        $this->pastoralTagMapper->save($tag);
                    }
                }

                $this->pastoralTagMapper->assignToRecord($record, $tag);
            }
        }
    }

    /**
     * Insert a pastoral care record
     *
     * @throws RouteNotFound
     * @throws Exception
     */
    public function postRecordInsert(): void
    {
        $post = $this->getRequest()->post;

        $studentId           = $post["student_id"];
        $duplicateStudentIds = $post["duplicate_id"];

        // Check if we need to create any duplicates

        if (is_array($duplicateStudentIds)) {
            $duplicates = [];

            foreach ($duplicateStudentIds as $duplicateStudentId) {
                if (trim($duplicateStudentId) != '' && $duplicateStudentId != $studentId) {
                    $duplicates[] = $duplicateStudentId;
                }
            }

            // Create new pastoral record with duplicates

            $this->processPastoralRecords($studentId, $post, $duplicates);
        } else {
            // Create new pastoral record

            $this->processPastoralRecords($studentId, $post);
        }

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoral.getStudent', [
                'studentId' => $studentId,
            ])
        );
    }

    /**
     * Update a pastoral care record
     *
     * @throws MapperException
     * @throws RouteNotFound
     * @throws Exception
     */
    public function postRecordUpdate(): void
    {
        $post = $this->getRequest()->post;

        // Grab the pastoral record we are updating

        try {
            /** @var Model $record */
            $record = $this->pastoralDataSource->findById($this->getUser(), $post['id']);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        $studentId           = $post["student_id"];
        $duplicateStudentIds = $post["duplicate_id"];

        // Check if we need to create any duplicates

        $duplicates = [];

        if (is_array($duplicateStudentIds)) {
            foreach ($duplicateStudentIds as $duplicateStudentId) {
                if (trim($duplicateStudentId) != '') {
                    $duplicates[] = $duplicateStudentId;
                }
            }
        }

        $this->processPastoralRecords($studentId, $post, $duplicates, $record);

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoral.getStudent', [
                'studentId' => $studentId,
            ])
        );
    }

    /**
     * Create/update student records
     *
     * @param int           $primaryStudentId    The primary records student ID
     * @param RequestValues $post                POST parameters passed by the create/update form
     * @param array         $duplicateStudentIds List of students the primary record should be duplicated on
     * @param PastoralModel $parent              Parent record (if we are updating an existing record)
     *
     * @return Collection|PastoralModel[]
     * @throws MapperException
     * @throws Exception
     */
    private function processPastoralRecords($primaryStudentId, $post, array $duplicateStudentIds = [], PastoralModel $parent = null)
    {
        // External interface

        $pastoralExternal = new External_Pastoral();

        // Store the records we create

        /** @var Collection|PastoralModel[] $records */
        $records = new Collection();

        // Make sure the primary records student ID is at the start of the array so we create it first

        array_unshift($duplicateStudentIds, $primaryStudentId);

        // Go through every kid

        foreach ($duplicateStudentIds as $studentIdCurrent) {
            if ($parent instanceof PastoralModel && $studentIdCurrent == $primaryStudentId) {
                // Updating existing pastoral record

                $record = $parent;
            } else {
                // Creating a new record

                $record = $this->pastoralDataSource->createModel();

                if (isset($parent)) {
                    $record->setParent($parent);
                }
            }

            try {
                /** @var User $student */
                $student = $this->userMapper->findById($studentIdCurrent);
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

            // Only set owner if this is a new record
            if (!$record->getId()) {
                $record->setOwner($this->getUser());
            }
            $this->pastoralDataSource->save($record);

            $records->add($record);

            // Store the first records ID for use as parent for subsequent copies

            if (!isset($parent)) {
                $parent = $record;
            }

            // Save pastoral tags

            $this->pastoralTagMapper->deleteForRecord($record);
            $this->assignTagsToRecord($record, $post["tags"]);
            $this->pastoralTagMapper->purgeUnassociated();

            // Save permissions/visibility

            $this->pastoralAccessMapper->deleteByColumn('pastoral_id', $record->getId());

            // Save system role permissions, de-duplicating as we go

            $foundRoles = [];

            if (is_array($post["role"])) {
                foreach ($post["role"] as $role_id => $accessLevel) {
                    if (!in_array($role_id, $foundRoles)) {
                        $foundRoles[] = $role_id;

                        try {
                            /** @var Role $role */
                            $role = $this->roleMapper->findById($role_id);
                            $access = $this->pastoralAccessMapper->createModel();
                            $access->setPastoral($record);
                            $access->setAccess($accessLevel);
                            $access->setRole($role);
                            $this->pastoralAccessMapper->save($access);
                        } catch (MapperException $e) {
                            // Noop
                        }
                    }
                }
            }

            // Save pastoral group permissions, de-duplicating as we go

            $foundGroups = [];

            if (is_array($post["group"])) {
                foreach ($post["group"] as $group_id => $accessLevel) {
                    if (!in_array($group_id, $foundGroups)) {
                        $foundGroups[] = $group_id;

                        try {
                            /** @var PastoralGroup $group */
                            $group = $this->pastoralGroupMapper->findById($group_id);
                            $access = $this->pastoralAccessMapper->createModel();
                            $access->setPastoral($record);
                            $access->setAccess($accessLevel);
                            $access->setGroup($group);
                            $this->pastoralAccessMapper->save($access);
                        } catch (MapperException $e) {
                            // Noop
                        }
                    }
                }
            }

            // Save individual user permissions, de-duplicating as we go

            $foundUsers = [];

            if (is_array($post["user"])) {
                foreach ($post["user"] as $user_id => $accessLevel) {
                    if (!in_array($user_id, $foundUsers)) {
                        $foundUsers[] = $user_id;

                        try {
                            /** @var UserMapper $user */
                            $user = $this->userMapper->findById($user_id);
                            $access = $this->pastoralAccessMapper->createModel();
                            $access->setPastoral($record);
                            $access->setAccess($accessLevel);
                            $access->setUser($user);
                            $this->pastoralAccessMapper->save($access);
                        } catch (MapperException $e) {
                            // Noop
                        }
                    }
                }
            }

            // Save record in external database, and set pastoral external_id in Schoolbox database

            $pastoralExternal->syncPastoral($record);
        }

        /** @var Model $record */
        foreach ($records as $record) {
            // Save public attachments
            $this->persistAttachments($record, $post, true);

            // Save private attachments
            $this->persistAttachments($record, $post, false);
        }

        return $records;
    }

    /**
     * Delete a pastoral care record
     *
     * @param PastoralModel $record
     *
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function postRecordDelete($record): void
    {
        $studentId = $record->getStudent()->getId();

        /* Normally we would apply this logic in the mapper, but in this case we intentionally
         * do so in the Controller as here we are acting on a single record.
         * The specific risk is that during import, the system first deletes all
         * schoolbox records that map to a SIS pastoral record. If this logic
         * is on the mapper, it catastrophical deletes the records in both systems.
         * Having the logic here also aligns with the approach taken for create/update SB => SIS.
         */
        if ($this->pastoralDataSource->delete($record)) {
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
    private function getAttachZone($name, $record = null): array
    {
        $zone = new Zone($name);
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select attachment')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload attachment')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setFileVar("{$name}_attachments[]")
            ->setMulti(true);

        // Attach any existing files

        if ($record instanceof PastoralModel) {
            if ($record->getAttachments() instanceof Collection) {
                /** @var PastoralAttachmentModel $attachment */
                foreach ($record->getAttachments() as $attachment) {
                    if ($name === self::ATTACHZONE_PRIVATE && $attachment->getPrivate()) {
                        $zone->attachFiles([$attachment]);
                    } elseif ($name === self::ATTACHZONE_PUBLIC && !$attachment->getPrivate()) {
                        $zone->attachFiles([$attachment]);
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
    public function getStudentRecordAttachment(PastoralAttachmentModel $attachment): void
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

    /**
     * Persist pastoral record attachments.
     *
     * @param RequestValues $post      POST request vars
     * @param PastoralModel $record    The record to persist
     * @param bool          $isPrivate Is this a private or public attachment?
     * @throws MapperException
     */
    private function persistAttachments(PastoralModel $record, RequestValues $post, bool $isPrivate): void
    {
        $attachzone = $isPrivate ? self::ATTACHZONE_PRIVATE : self::ATTACHZONE_PUBLIC;

        // preparation
        $oldAttachments = $this->pastoralAttachmentMapper
            ->findByColumn('pastoral_id', $record->getId())
            ->filterBy('private', $isPrivate);
        $newAttachments = $this->pastoralAttachmentMapper->createCollection();
        $diffMethod = function (PastoralAttachmentModel $a, PastoralAttachmentModel $b) {
            return $a->getStorage()->getHash() === $b->getStorage()->getHash();
        };

        // If there are any new attachments, compare and replace
        if (is_array($post[$attachzone . '_attachments'])) {
            $collectorFactory  = new CollectorFactory();
            $attachmentObjects = $collectorFactory->build($post[$attachzone . '_attachments'])->getAll();

            /** @var Storage $storageObject */
            foreach ($attachmentObjects as $storageObject) {
                // Only add attachments which don't already exist
                $attachment = $this->pastoralAttachmentMapper->createModel();
                $attachment->setPastoral($record);
                $attachment->setStorage($storageObject);
                $attachment->setPrivate($isPrivate);
                $newAttachments->add($attachment);
            }

            // Save new attachments that aren't in old
            $this->pastoralAttachmentMapper->save($newAttachments->diff($oldAttachments, $diffMethod));
        }
        // Delete old attachments that aren't in new
        $this->pastoralAttachmentMapper->delete($oldAttachments->diff($newAttachments, $diffMethod));
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

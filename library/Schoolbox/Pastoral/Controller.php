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
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperDeletedException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Count\Helper as PastoralCountHelper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Revision\DataSource as PastoralRevisionDataSource;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTagsModel;
use Schoolbox\Pastoral\Action\Mapper as PastoralActionMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Search\Results;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\TokenReplacer;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /** @var IConfigLoader $config */
    protected IConfigLoader $config;

    /** @var ErrorMapper $errorMapper */
    protected ErrorMapper $errorMapper;

    /** @var FileMapper $fileMapper */
    protected FileMapper $fileMapper;

    /** @var PastoralDataSource $pastoralDataSource */
    protected PastoralDataSource $pastoralDataSource;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected PastoralAccessMapper $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    protected PastoralGroupMapper $pastoralGroupMapper;

    /** @var PastoralGroupMemberMapper $pastoralGroupMemberMapper */
    protected PastoralGroupMemberMapper $pastoralGroupMemberMapper;

    /** @var PastoralAttachmentMapper $pastoralAttachmentMapper */
    protected PastoralAttachmentMapper $pastoralAttachmentMapper;

    /** @var PastoralAuditMapper $pastoralAuditMapper */
    protected PastoralAuditMapper $pastoralAuditMapper;

    /** @var PastoralSubTypeMapper $pastoralSubTypeMapper */
    protected PastoralSubTypeMapper $pastoralSubTypeMapper;

    /** @var PastoralCommentMapper $pastoralCommentMapper */
    protected PastoralCommentMapper $pastoralCommentMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    protected PastoralSeverityMapper $pastoralSeverityMapper;

    /** @var PastoralRevisionDataSource $pastoralRevisionDataSource */
    protected PastoralRevisionDataSource $pastoralRevisionDataSource;

    /** @var PastoralTagMapper $pastoralTagMapper */
    protected PastoralTagMapper $pastoralTagMapper;

    /** @var PastoralActionMapper */
    protected PastoralActionMapper $pastoralActionMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    protected PastoralTypeMapper $pastoralTypeMapper;

    /** @var RecordAction\Mapper $pastoralRecordActionMapper */
    private RecordAction\Mapper $pastoralRecordActionMapper;

    /** @var PastoralUploadQueueMapper $pastoralUploadQueueMapper */
    protected PastoralUploadQueueMapper $pastoralUploadQueueMapper;

    /** @var RoleMapper $roleMapper */
    protected RoleMapper $roleMapper;

    /** @var UserMapper $userMapper */
    protected UserMapper $userMapper;

    /** @var UserAccessMapper $userAccessMapper */
    protected UserAccessMapper $userAccessMapper;

    /** @var PastoralReportMapper $pastoralReportMapper */
    private PastoralReportMapper $pastoralReportMapper;

    /** @var Audience\Mapper */
    private Audience\Mapper $audienceMapper;

    /** TermMapper */
    private TermMapper $termMapper;

    /**
     * Pastoral comment translator
     */
    private Comment\Translator\ModelToArray $pastoralCommentTranslator;

    const ATTACHZONE_PRIVATE = 'private';
    const ATTACHZONE_PUBLIC  = 'public';

    /**
     * Constructor
     *
     * @param IConfigLoader                   $config
     * @param FileMapper                      $fileMapper
     * @param PastoralDataSource              $pastoralDataSource
     * @param UserAccessMapper                $userAccessMapper
     * @param PastoralAuditMapper             $pastoralAuditMapper
     * @param PastoralSubTypeMapper           $pastoralSubTypeMapper
     * @param PastoralTypeMapper              $pastoralTypeMapper
     * @param PastoralUploadQueueMapper       $pastoralUploadQueueMapper
     * @param PastoralSeverityMapper          $pastoralSeverityMapper
     * @param PastoralRevisionDataSource      $pastoralRevisionDataSource
     * @param PastoralTagMapper               $pastoralTagMapper
     * @param PastoralActionMapper            $pastoralActionMapper
     * @param RecordAction\Mapper             $pastoralRecordActionMapper
     * @param PastoralCommentMapper           $pastoralCommentMapper
     * @param PastoralAttachmentMapper        $pastoralAttachmentMapper
     * @param RoleMapper                      $roleMapper
     * @param UserMapper                      $userMapper
     * @param PastoralAccessMapper            $pastoralAccessMapper
     * @param PastoralGroupMapper             $pastoralGroupMapper
     * @param PastoralGroupMemberMapper       $pastoralGroupMemberMapper
     * @param ErrorMapper                     $errorMapper
     * @param PastoralReportMapper            $pastoralReportMapper
     * @param Audience\Mapper                 $audienceMapper
     * @param TermMapper                      $termMapper
     * @param Comment\Translator\ModelToArray $pastoralCommentTranslator
     * @param FilesystemProxy                 $filesystemProxy
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
        PastoralActionMapper $pastoralActionMapper,
        \Schoolbox\Pastoral\RecordAction\Mapper $pastoralRecordActionMapper,
        PastoralCommentMapper $pastoralCommentMapper,
        PastoralAttachmentMapper $pastoralAttachmentMapper,
        RoleMapper $roleMapper,
        UserMapper $userMapper,
        PastoralAccessMapper $pastoralAccessMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        ErrorMapper $errorMapper,
        PastoralReportMapper $pastoralReportMapper,
        Audience\Mapper $audienceMapper,
        TermMapper $termMapper,
        Comment\Translator\ModelToArray $pastoralCommentTranslator,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->config                     = $config;
        $this->errorMapper                = $errorMapper;
        $this->fileMapper                 = $fileMapper;
        $this->pastoralAuditMapper        = $pastoralAuditMapper;
        $this->pastoralAccessMapper       = $pastoralAccessMapper;
        $this->pastoralGroupMapper        = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper  = $pastoralGroupMemberMapper;
        $this->pastoralAttachmentMapper   = $pastoralAttachmentMapper;
        $this->pastoralSubTypeMapper      = $pastoralSubTypeMapper;
        $this->pastoralCommentMapper      = $pastoralCommentMapper;
        $this->pastoralDataSource         = $pastoralDataSource;
        $this->pastoralSeverityMapper     = $pastoralSeverityMapper;
        $this->pastoralRevisionDataSource = $pastoralRevisionDataSource;
        $this->pastoralTagMapper          = $pastoralTagMapper;
        $this->pastoralActionMapper       = $pastoralActionMapper;
        $this->pastoralRecordActionMapper = $pastoralRecordActionMapper;
        $this->pastoralTypeMapper         = $pastoralTypeMapper;
        $this->pastoralUploadQueueMapper  = $pastoralUploadQueueMapper;
        $this->roleMapper                 = $roleMapper;
        $this->userMapper                 = $userMapper;
        $this->userAccessMapper           = $userAccessMapper;
        $this->pastoralReportMapper       = $pastoralReportMapper;
        $this->audienceMapper             = $audienceMapper;
        $this->termMapper                 = $termMapper;
        $this->pastoralCommentTranslator  = $pastoralCommentTranslator;
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
        $groupsMemberships->sortByCallback(
            function (PastoralGroupMember $a, PastoralGroupMember $b) {
                return strnatcasecmp($a->getGroup()->getName(), $b->getGroup()->getName());
            }
        );

        $this->getResponse()->content->set(
            [
                'template' => '@pastoral/index.twig',
                'data'     => [
                    'user'             => $user,
                    'recentStudents'   => $this->pastoralAuditMapper->findRecentlyViewedStudentsForUser($user),
                    'recentDashboard'  => $this->pastoralDataSource->findRecentDashboard($user),
                    'reportsDashboard' => $this->pastoralReportMapper->findForUserDashboard($user),
                    'groups'           => $groupsMemberships,
                ],
                'title'    => 'Dashboard',
            ]
        );
    }

    /**
     * Display pastoral care search results
     *
     * @throws MapperException
     */
    public function getSearch(): void
    {
        $get = $this->getRequest()->query;

        $this->getResponse()->content->set(
            [
                'template' => '@pastoral/search.twig',
                'data'     => [
                    'search'              => trim($get['keyword']),
                    'results'             => $this->pastoralDataSource->getSearchResults($this->getUser(), $get['keyword']),
                    'students'            => $this->userMapper->findStudents($get['keyword']),
                    'minimumSearchLength' => Results::MIN_SEARCH_LENGTH,
                ],
                'title'    => 'Search',
            ]
        );
    }

    /**
     * return a summary of all pastoral events for a collection of students that
     * the logged-in user has permission to view
     *
     * @param Collection $students
     *
     * @throws RouteNotFound
     * @api   GET /pastoral/students/count/{studentIds:[\d\x2c]+}
     * @since 21.1
     */
    public function getStudentsCount(Collection $students): void
    {
        $pastoralCountHelper = new PastoralCountHelper($this->router, $this->pastoralTypeMapper, $this->pastoralSubTypeMapper, $this->pastoralDataSource, $this->termMapper);
        $output              = $pastoralCountHelper->countStudentsEventsByTypes(
            $this->getUser(),
            $students,
            null,
            null,
            $pastoralCountHelper->getStartDate((int)$this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_FLAG_COUNT_PERIOD))
        );

        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Display an individual students pastoral records
     *
     * @param User $student
     *
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function getStudent($student): void
    {
        $get = $this->getRequest()->query;

        // Create an audit record of anyone viewing a student record
        // Hydrate user with group membership
        $user = $this->getUser();

        $auditRecord = $this->pastoralAuditMapper->createModel();
        $auditRecord->setStudent($student);
        $auditRecord->setUser($user);
        $this->pastoralAuditMapper->save($auditRecord);

        $typeId    = (isset($get['type']) && is_numeric($get['type'])) ? (int)$get['type'] : null;
        $subtypeId = (isset($get['subtype']) && is_numeric($get['subtype'])) ? (int)$get['subtype'] : null;

        // Limit the number of pastoral records to display on each page of pastoral records
        $resultsPerPage = (int)$this->config->get('pastoral_page_size');

        // Set the current page number
        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? (int)$get['pageCurrent'] : 1;

        $recordCount = $this->pastoralDataSource->countRecords($user, $student->getId(), $typeId, $subtypeId);

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
        $pageRecords = $this->pastoralDataSource->findRecords($user, $student->getId(), $typeId, $subtypeId, ($pageCurrent - 1) * $resultsPerPage, $resultsPerPage, null);

        $pastoralCountHelper = new PastoralCountHelper($this->router, $this->pastoralTypeMapper, $this->pastoralSubTypeMapper, $this->pastoralDataSource, $this->termMapper);
        $countSummaryByMonth = $pastoralCountHelper->countStudentEventsByTypesByMonth($this->getUser(), $student, $pastoralCountHelper->getStartDate($pastoralCountHelper::COUNT_PERIOD_12MONTHS));

        $this->getResponse()->content->set(
            [
                'template' => '@pastoral/student.twig',
                'data'     => [
                    'user'                => $user,
                    'recordCount'         => $recordCount,
                    'records'             => $pageRecords,
                    'student'             => $student,
                    'pageCurrent'         => $pageCurrent,
                    'types'               => $this->pastoralTypeMapper->findAll(),
                    'subtypes'            => $this->pastoralSubTypeMapper->findAll(),
                    'countSummaryByMonth' => [
                        'recordCount' => count($countSummaryByMonth),
                        'series'      => $pastoralCountHelper->buildMonthlyBarChart($countSummaryByMonth),
                    ],
                    'typeSelected'        => $typeId,
                    'subtypeSelected'     => $subtypeId,
                    'resultsPerPage'      => $resultsPerPage,
                    'pagesToDisplay'      => $maxPage,
                ],
                'title'    => $student->getFullName(),
            ]
        );
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

        $typeId    = is_numeric($get['type']) ? (int)$get['type'] : null;
        $subtypeId = is_numeric($get['subtype']) ? (int)$get['subtype'] : null;

        // Limit the number of pastoral records to display on each page of pastoral records
        $resultsPerPage = (int)$this->config->get('pastoral_page_size');

        // Set the current page number
        $pageCurrent = isset($get['pageCurrent']) && is_numeric($get['pageCurrent']) ? (int)$get['pageCurrent'] : 1;

        // Retrieve all of the students pastoral records
        $recordCount  = 0;
        $linkedCounts = $this->pastoralDataSource->getLinkedStudentRecordCount($this->getUser(), $student->getId(), $typeId, $subtypeId);
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
        $pageRecords = $this->pastoralDataSource->findRecords($this->getUser(), $student->getId(), $typeId, $subtypeId, ($pageCurrent - 1) * $resultsPerPage, $resultsPerPage, $linkedStudent->getId());

        $this->getResponse()->content->set(
            [
                'template' => '@pastoral/student_linked.twig',
                'data'     => [
                    'user'            => $this->getUser(),
                    'recordCount'     => $recordCount,
                    'records'         => $pageRecords,
                    'student'         => $student,
                    'linkedStudent'   => $linkedStudent,
                    'pageCurrent'     => $pageCurrent,
                    'types'           => $this->pastoralTypeMapper->findAll(),
                    'subtypes'        => $this->pastoralSubTypeMapper->findAll(),
                    'linkedStudents'  => $linkedCounts,
                    'typeSelected'    => $typeId,
                    'subtypeSelected' => $subtypeId,
                    'resultsPerPage'  => $resultsPerPage,
                    'pagesToDisplay'  => $maxPage,
                ],
                'title'    => 'Linked Records',
            ]
        );
    }

    /**
     * Display a single pastoral care record
     *
     * @param Model $record
     * @param User  $student
     *
     * @throws MapperException
     * @throws Exception
     * @api /pastoral/student/{studentId:\d+}/record/{recordId:\d+}
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
            foreach ($record->getAccessWithPermission() as $access) {
                if ($access->getGroup() instanceof PastoralGroup) {
                    $this->pastoralGroupMapper->findById($access->getGroup()->getId());
                    foreach ($access->getGroup()->getMembersWithPermission() as $member) {
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

        $this->getResponse()->content->set(
            [
                'template' => '@pastoral/record.twig',
                'data'     => [
                    'record'             => $record,
                    'attachmentsPublic'  => $attachmentsPublic,
                    'attachmentsPrivate' => $attachmentsPrivate,
                    'commentsPublic'     => $commentsPublic->map($this->pastoralCommentTranslator)->reindex(),
                    'commentsPrivate'    => $commentsPrivate->map($this->pastoralCommentTranslator)->reindex(),
                    'moderator'          => $moderator,
                    'user'               => $this->getUser(),
                    'showSeverity'       => $this->getConfigLoader()->get('pastoral_severity'),
                    'access'             => $access,
                    'linkedStudents'     => [],
                ],
                'title'    => $record->getHeading(),
            ]
        );
    }

    /**
     * Insert pastoral care record
     *
     * @param User|null $student // can be null, if requested from classlist
     * @throws MapperException
     */
    public function getStudentRecordInsertForm(?User $student): void
    {
        // Throw in a blank pastoral record with the student populated

        $record = $this->pastoralDataSource->createModel();
        if ($student) {
            $record->setStudent($student);
        }

        $this->getStudentRecordUpdateForm($record);
    }


    /**
     * Update pastoral care record
     *
     * @param Model $record
     *
     * @throws \JsonException
     */
    public function getStudentRecordUpdateForm($record): void
    {
        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            $severities = $this->pastoralSeverityMapper->findAll();
        }

        // Check if user is a pastoral moderator

        $moderator = $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0;

        $allowParents  = $this->config->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED);
        $allowStudents = $this->config->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED);

        // Tally counts for staff roles:
        // for parent and student roles, only count if there is a user
        $roles          = new Collection();
        $roleUserCounts = [];
        $rolesWithUsers = [];

        $allowedRoles   = $this->pastoralAccessMapper->determineAllowedRoles();

        // When student ids are provided to copy this record to:
        // add all those students and their parents to the role user counts.
        $duplicateIds = explode(',', $this->request->query->get('duplicates') ?? '');
        /** @var User[] $duplicates */
        $duplicates = $this->userMapper->findByColumnList([
            'id'           => $duplicateIds,
            'g.group_type' => Role::STUDENT,
        ]);

        /** @var Role $roleCurrent */
        foreach ($allowedRoles as $roleCurrent) {
            $rolesWithUsers[$roleCurrent->getId()] = false;
            $roleUserCounts[$roleCurrent->getId()] = 0;

            // Staff role? Show user count
            if ($roleCurrent->isStaff() && $roleCurrent->getAccessPastoral()) {
                $roles->add($roleCurrent);
                $roleUserCounts[$roleCurrent->getId()] = $this->userMapper->countByColumn('group_id', $roleCurrent->getId());
                $rolesWithUsers[$roleCurrent->getId()] = true;
            }

            // Parent role?
            // Only track if there are users in the role who are a parent of a
            // student, not the number
            if ($allowParents && $roleCurrent->isParent()) {
                $roles->add($roleCurrent);
                if ($record->getStudent()) {
                    foreach ($record->getStudent()->getParents() as $parent) {
                        if ($parent->getRole()->getId() == $roleCurrent->getId()) {
                            $rolesWithUsers[$roleCurrent->getId()] = true;
                            break;
                        }
                    }
                }
                foreach ($duplicates as $duplicateStudent) {
                    if ($rolesWithUsers[$roleCurrent->getId()]) {
                        break;
                    }
                    $duplicateStudent->loadParents();
                    foreach ($duplicateStudent->getParents() as $parent) {
                        if ($parent->getRole()->getId() == $roleCurrent->getId()) {
                            $rolesWithUsers[$roleCurrent->getId()] = true;
                        }
                    }
                }
            }
        }

        $audiences        = $this->audienceMapper->findAll()->filter($this->audienceMapper->getPermissionCallback());
        $typeAudiences    = [];
        $subtypeAudiences = [];
        foreach ($audiences as $audience) {
            if ($audience->getSubtype()) {
                $subtypeAudiences[$audience->getSubtype()->getId()][] = $audience;
            } else {
                $typeAudiences[$audience->getType()->getId()][] = $audience;
            }
        }

        // Prefill create duplicate record field with duplicate students
        $students = [];
        foreach ($duplicates as $student) {
            $students[] = [
                'id'   => $student->getId(),
                'name' => $student->getFullName(),
            ];
        }

        /* Build a hash lookup of (typeId => html) and (subtypeId => html) tuples
         * for the front end's dropdowns. When those dropdowns change, sometimes
         * the General Template should get updated and the easiest way is to simply
         * perform an id lookup
         */
        $types    = $this->pastoralTypeMapper->findAll();
        $subtypes = $this->pastoralSubTypeMapper->findAll();

        $typesIndex       = [];
        $typesIndexPrivate       = [];
        $audienceDefaults = [];

        foreach ($types as $type) {
            $typesIndex[$type->getId()] = $type->getTemplate();
            $typesIndexPrivate[$type->getId()] = $type->getTemplatePrivate();
            if (key_exists($type->getId(), $typeAudiences) || $type->getVisibilityLocked()) {
                $audienceDefaults[] = [
                    'typeId'    => $type->getId(),
                    'subtypeId' => 0,
                    'audiences' => $typeAudiences[$type->getId()] ?? [],
                ];
            }
        }

        $subtypesIndex = [];
        $subtypesIndexPrivate = [];

        foreach ($subtypes as $subtype) {
            $subtypesIndex[$subtype->getId()] = $subtype->getTemplate();
            $subtypesIndexPrivate[$subtype->getId()] = $subtype->getTemplatePrivate();
            if (key_exists($subtype->getId(), $subtypeAudiences) || $subtype->getVisibilityLocked()) {
                $audienceDefaults[] = [
                    'typeId'    => $subtype->getPastoralType()->getId(),
                    'subtypeId' => $subtype->getId(),
                    'audiences' => $subtypeAudiences[$subtype->getId()] ?? [],
                ];
            }
        }

        // Render this thing out

        $this->getResponse()->content->set(
            [
                'template' => '@pastoral/modify.twig',
                'data'     => [
                    'record'            => $record,
                    'canCreate'         => $this->getConfigLoader()->get('pastoral_create_tag_enable'),
                    'allowIndividual'   => $this->config->get('pastoral_individual_access'),
                    'allowParents'      => $allowParents,
                    'allowStudents'     => $allowStudents,
                    'types'             => $types,
                    'typesIndexPrivate' => $typesIndexPrivate,
                    'typesIndex'        => $typesIndex,
                    'subTypes'          => $subtypes,
                    'subtypesIndexPrivate'  => $subtypesIndexPrivate,
                    'subtypesIndex'     => $subtypesIndex,
                    'severities'        => $severities,
                    'roles'             => $roles,
                    'roleTypes'         => $this->pastoralAccessMapper->determineAllowedRoleTypes(),
                    'roleUserCounts'    => $roleUserCounts,
                    'rolesWithUsers'    => $rolesWithUsers,
                    'groups'            => $this->pastoralGroupMapper->findAll()->sortNaturally('name'),
                    'moderator'         => $moderator,
                    'moderatorIds'      => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)->getPropertyValues('id'),
                    'attachzonePublic'  => $this->getAttachZone(self::ATTACHZONE_PUBLIC, $record),
                    'attachzonePrivate' => $this->getAttachZone(self::ATTACHZONE_PRIVATE, $record),
                    'audiences'         => $audienceDefaults,
                    'students'          => json_encode($students),
                    'returnUrlName'     => Redirect::RETURN_URL,
                    'encodedReturnUrl'  => $this->request->query->get(Redirect::RETURN_URL),
                ],
                'title'    => 'Pastoral Record',
            ]
        );
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
     * @param Model $record
     *
     * @throws SchoolboxException
     * @throws Exception
     */
    public function postCommentInsert(Model $record): void
    {
        $post = $this->request->post;
        /**
         * confirm if the user can view / edit the private part of the record
         */
        $accessLevel  = (new AccessLevelCensor())->getAccessLevel($record, $this->getUser());
        $allowPrivate = $accessLevel > PastoralAccessMapper::ACCESS_PUBLIC;

        /**
         * if the user is does not have private access, and is trying to set a private comment, fail
         */
        if (!$allowPrivate && $post->get('comment_private')) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_PERM_RECORD);
        } else if (!empty(trim($post->get('comment_body')))) {
            // Insert the comment to the internal database
            $comment = $this->pastoralCommentMapper->createModel();
            $comment->setPastoral($record);
            $comment->setBody(trim($post->get('comment_body')));
            $comment->setOwner($this->getUser());
            $comment->setPrivate($post->get('comment_private'));
            $this->pastoralCommentMapper->save($comment);

            // Insert the comment to the external database
            $pastoralExternal = new External_Pastoral();
            $pastoralExternal->createComment($comment);

            // Post a JSON response
            $this->response->content->set(
                $this->buildJsonOutput(
                    call_user_func($this->pastoralCommentTranslator, $comment)
                )
            );
        } else {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }

    /**
     * Assign tags to a record, spawning them if required, or resurrecting them
     * if these tags once existed
     *
     * @param string $tagCsv
     * @return Collection
     * @throws MapperException
     */
    private function prepareTags(string $tagCsv): Collection
    {
        $tagNames = explode(",", $tagCsv);
        $tags     = $this->pastoralTagMapper->createCollection();

        $canCreate = (bool)$this->getConfigLoader()->get('pastoral_create_tag_enable');

        foreach ($tagNames as $tagCurrent) {
            if (trim($tagCurrent) != '') {
                $tag = null;
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
                }

                /* Tag doesn't exist - spawn it, save it, then assign it
                 */
                if (!$tag && $canCreate) {
                    $tag = new PastoralTagsModel();
                    $tag->setName($tagCurrent);
                    $this->pastoralTagMapper->save($tag);
                }

                if ($tag) {
                    $tags->add($tag);
                }
            }
        }
        return $tags;
    }

    /**
     * @param RequestValues $post
     * @return Collection
     * @throws MapperException
     */
    private function prepareAccesses(RequestValues $post): Collection
    {
        // Save system role permissions, de-duplicating as we go
        $accesses = $this->pastoralAccessMapper->createCollection();

        if (is_array($post["role"] ?? null)) {
            $foundRoles       = [];
            $allowedRoles     = $this->pastoralAccessMapper->determineAllowedRoles();
            $allowedRoleTypes = $this->pastoralAccessMapper->determineAllowedRoleTypes();
            foreach ($post["role"] as $roleId => $accessLevel) {
                if (in_array($roleId, $foundRoles)) {
                    // should not happen
                    continue;
                }
                $foundRoles[] = $roleId;

                $access = $this->pastoralAccessMapper->createModel();
                $access->setAccess($accessLevel);

                // Is this a role or a role type?
                if (preg_match('/^role-type-(?P<roleType>.*)$/', (string)$roleId, $matches)) {
                    if (!in_array($matches['roleType'], $allowedRoleTypes)) {
                        continue;
                    }
                    $access->setRoleType($matches['roleType']);
                } else {
                    $role = $allowedRoles[$roleId] ?? null;
                    if ($role) {
                        $access->setRole($role);
                    } else {
                        continue;
                    }
                }
                $accesses->add($access);
            }
        }

        // Save pastoral group permissions, de-duplicating as we go
        if (is_array($post["group"] ?? null)) {
            $foundGroups = [];
            foreach ($post["group"] as $group_id => $accessLevel) {
                if (in_array($group_id, $foundGroups)) {
                    // should not happen
                    continue;
                }
                $foundGroups[] = $group_id;
                try {
                    $group  = $this->pastoralGroupMapper->findById($group_id);
                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setAccess($accessLevel);
                    $access->setGroup($group);
                    $accesses->add($access);
                } catch (MapperException $e) {
                    // Noop
                }
            }
        }

        // Save individual user permissions, de-duplicating as we go
        if (is_array($post["user"] ?? null)) {
            $foundUsers = [];
            foreach ($post["user"] as $user_id => $accessLevel) {
                if (in_array($user_id, $foundUsers)) {
                    // should not happen
                    continue;
                }
                $foundUsers[] = $user_id;
                try {
                    $user   = $this->userMapper->findById($user_id);
                    $access = $this->pastoralAccessMapper->createModel();
                    $access->setAccess($accessLevel);
                    $access->setUser($user);
                    $accesses->add($access);
                } catch (MapperException $e) {
                    // Noop
                }
            }
        }

        return $accesses;
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

        $studentId           = (int)$post->get('student_id');
        $duplicateStudentIds = array_filter(
            array_map(
                'trim',
                explode(',', $post->get('duplicateIds') ?? '')
            ),
            'strlen',
        );
        if (empty($studentId) && empty($duplicateStudentIds)) {
            throw new InvalidArgumentException('No student provided.');
        }
        if (empty($studentId)) {
            $studentId = (int)array_shift($duplicateStudentIds);
        }

        // Check if we need to create any duplicates

        $duplicates = [];

        foreach ($duplicateStudentIds as $duplicateStudentId) {
            if (trim($duplicateStudentId) != '' && $duplicateStudentId != $studentId) {
                $duplicates[] = $duplicateStudentId;
            }
        }

        // Create new pastoral record with duplicates

        $this->processPastoralRecords($studentId, $post, $duplicates);

        // Go back to students pastoral record
        $encodedReturnUrl = $post->get(Redirect::RETURN_URL);
        if (!empty($encodedReturnUrl)) {
            $returnUrl = Redirect::decode($encodedReturnUrl);
        } else {
            $returnUrl = $this->getRouter()->generate('pastoral.getStudent', ['studentId' => $studentId,]);
        }
        $this->getResponse()->redirect->afterPost($returnUrl);
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
            $record = $this->pastoralDataSource->findById($this->getUser(), $post->get('id'));
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        $studentId           = (int)$post->get('student_id');
        $duplicateStudentIds = explode(',', $post->get('duplicateIds') ?? '');

        // Check if we need to create any duplicates

        $duplicates = [];

        foreach ($duplicateStudentIds as $duplicateStudentId) {
            if (trim($duplicateStudentId) != '') {
                $duplicates[] = $duplicateStudentId;
            }
        }

        $this->processPastoralRecords($studentId, $post, $duplicates, $record);

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoral.getStudent', ['studentId' => $studentId,])
        );
    }

    /**
     * Create/update student records
     *
     * @param int                $primaryStudentId    The primary records student ID
     * @param RequestValues      $post                POST parameters passed by the create/update form
     * @param array              $duplicateStudentIds List of students the primary record should be duplicated on
     * @param PastoralModel|null $parent              Parent record (if we are updating an existing record)
     *
     * @return void
     * @throws MapperException
     * @throws Exception
     */
    private function processPastoralRecords(int $primaryStudentId, RequestValues $post, array $duplicateStudentIds = [], PastoralModel $parent = null): void
    {
        $heading = trim($post->get('heading'));
        if (empty($heading)) {
            throw new InvalidArgumentException('Title cannot be empty');
        }

        // Make sure the primary records student ID is at the start of the array so we create it first
        array_unshift($duplicateStudentIds, $primaryStudentId);

        // Prepare type. No database change.
        try {
            $type = $this->pastoralTypeMapper->findById($post->get('typeId'));
        } catch (MapperException\Deleted $e) {
            /** @var Type\Model $type */
            $type = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        // Prepare subtype. No database change.
        $subtypeId = $post->get('subtypeId');
        $subtype   = null;
        if (!empty($subtypeId)) {
            try {
                $subtype = $this->pastoralSubTypeMapper->findById($subtypeId);
            } catch (MapperException\Deleted $e) {
                /** @var SubType\Model $subtype */
                $subtype = $e->getDeletedObject();
            } catch (MapperException $e) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }
            // Ensure that subtype belongs to the parent type:
            // if there is somehow a mismatch, clear the subtype
            if ($type->getId() !== $subtype->getPastoralType()->getId()) {
                $subtype = null;
            }
        }

        // Prepare Severity. No database change.
        $severity = null;
        if ($this->getConfigLoader()->get('pastoral_severity')) {
            try {
                $severity = $this->pastoralSeverityMapper->findById($post->get('severityId'));
            } catch (MapperException\Deleted $e) {
                /** @var Severity\Model $severity */
                $severity = $e->getDeletedObject();
            } catch (MapperException $e) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }
        }

        // Prepare access. No database change yet.
        $accesses = $this->prepareAccesses($post);

        // Prepare attachments. No database change yet.
        $newAttachments = $this->prepareAttachments($post);

        // Prepare actions.
        $newRecordActions = $this->prepareRecordActions($post);

        // Now ready to make changes
        $db = $this->pastoralTypeMapper->getDatabase();
        $db->beginTransaction();

        // Prepare tags. Save newly created tags.
        $tags = $this->prepareTags($post->get('tags'));

        $pastoralExternal = new External_Pastoral();

        // Go through every kid
        foreach ($duplicateStudentIds as $studentIdCurrent) {
            $previousRecord = null;
            if ($parent instanceof PastoralModel && $studentIdCurrent === $primaryStudentId) {
                // Updating existing pastoral record
                $record         = $parent;
                $previousRecord = clone $parent;
            } else {
                // Creating a new record
                $record = $this->pastoralDataSource->createModel();
                if (isset($parent)) {
                    $record->setParent($parent);
                }
            }

            // Find student
            try {
                $student = $this->userMapper->findById($studentIdCurrent);
            } catch (MapperException $e) {
                $db->rollback();
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
            }
            $record->setStudent($student);

            // If type was deleted, allow it to be saved only when it's not changed.
            if ($type->isDeleted() && ($record->getType()->getId() !== $type->getId())) {
                $db->rollback();
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }
            $record->setType($type);

            // If subtype was deleted, allow it to be saved only when it's not changed.
            if ($subtype && $subtype->isDeleted() && ($record->getSubType()->getId() !== $subtype->getId())) {
                $db->rollback();
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }
            $record->setSubType($subtype);

            // If severity was deleted, allow it to be saved only when it's not changed.
            if ($severity && $severity->isDeleted() && (!$record->getSeverity() || $record->getSeverity()->getId() !== $severity->getId())) {
                $db->rollback();
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }
            $record->setSeverity($severity);
            $record->setModifiedBy($this->getUser());
            $record->setHeading($heading);
            $record->setPoints($post->get('points'));

            /* Perform token replacements, basically a square bracket phplib
             * that performs a one-way substitution
             */
            $record->setBodyPublic(
                (new TokenReplacer\Student(new TokenReplacer\Simple()))
                    ->replace(trim($post->get('body_public')), $student)
            );

            /* Perform token replacements, basically a square bracket phplib
             * that performs a one-way substitution
             */
            $record->setBodyPrivate(
                (new TokenReplacer\Student(new TokenReplacer\Simple()))
                    ->replace(trim($post->get('body_private')), $student)
            );

            $record->setParentCommentsAllowed((bool)$post->get('parent_comments_allowed'));
            $record->setStudentCommentsAllowed((bool)$post->get('student_comments_allowed'));

            // Only set owner if this is a new record
            if (!$record->getId()) {
                $record->setOwner($this->getUser());
            }
            $this->pastoralDataSource->save($record);

            // Store the first records ID for use as parent for subsequent copies
            if (!isset($parent)) {
                $parent = $record;
            }

            // Save pastoral tags
            $this->pastoralTagMapper->deleteForRecord($record);
            foreach ($tags as $tag) {
                $this->pastoralTagMapper->assignToRecord($record, $tag);
            }
            $record->setTags($tags);

            // Save permissions/visibility
            $this->pastoralAccessMapper->deleteByColumn('pastoral_id', $record->getId());
            $record->setAccess($this->pastoralAccessMapper->createCollection());
            foreach ($accesses as $access) {
                $recordAccess = clone $access;
                $recordAccess->setPastoral($record);
                $this->pastoralAccessMapper->save($recordAccess);
                $record->getAccess()->add($recordAccess);
            }

            // Save actions, record actions are compared against luAction id rather than recordAction id.
            if ($this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $existingRecordActions = $record->getRecordActions()->indexByCallback(
                    static function ($recordAction) {
                        /** @var RecordAction\Model $recordAction */
                        return $recordAction->getAction()->getId();
                    }
                );
                $record->setRecordActions($this->pastoralRecordActionMapper->createCollection());
                /** @var RecordAction\Model $action */
                foreach ($newRecordActions as $action) {
                    // if this action is already set to this record, just put it back
                    if (in_array($action->getAction()->getId(), $existingRecordActions->keys())) {
                        $record->getRecordActions()->add($existingRecordActions->getOffset($action->getAction()->getId()));
                    } else {
                        $recordAction = clone $action;
                        $recordAction->setPastoral($record);
                        $this->pastoralRecordActionMapper->save($recordAction);
                        $record->getRecordActions()->add($recordAction);
                    }
                }
                // Delete actions no longer exists
                $removingRecordActions = $existingRecordActions->filter(
                    static function ($action) use ($newRecordActions) {
                        /** @var RecordAction\Model $action */
                        return !in_array($action->getAction()->getId(), $newRecordActions->keys());
                    }
                );
                $this->pastoralRecordActionMapper->deleteByColumn('id', $removingRecordActions->getPropertyValues('id'));
            }

            // preparation
            $oldAttachments       = $this->pastoralAttachmentMapper->findByColumn('pastoral_id', $record->getId());
            $newRecordAttachments = $this->pastoralAttachmentMapper->createCollection();
            foreach ($newAttachments as $newAttachment) {
                $newRecordAttachment = clone $newAttachment;
                $newRecordAttachment->setPastoral($record);
                $newRecordAttachments->add($newRecordAttachment);
            }

            [$createdAttachments, $deletedAttachments] = $this->pastoralAttachmentMapper->diffAttachments($newRecordAttachments, $oldAttachments);
            // Save new attachments that aren't in old
            $this->pastoralAttachmentMapper->save($createdAttachments);
            // Delete old attachments that aren't in new
            $this->pastoralAttachmentMapper->delete($deletedAttachments);
            $record->setAttachments($newRecordAttachments);

            $db->onPersist(
                function () use ($previousRecord, $record, $pastoralExternal) {
                    // raise notification
                    $action = $previousRecord ? \Schoolbox\Mapper::METHOD_UPDATE : \Schoolbox\Mapper::METHOD_CREATE;
                    $this->pastoralDataSource->resetPropagator();
                    $this->pastoralDataSource->raiseNotification($record, $action, $previousRecord);

                    // Save record in external database, and set pastoral external_id in Schoolbox database
                    $pastoralExternal->syncPastoral($record);
                }
            );
        }
        $db->commit();
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
            $this->getrouter()->generate('pastoral.getStudent', ['studentId' => $studentId,])
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
     * @return void
     * @throws \League\Flysystem\FilesystemException
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

        $basename = $attachment->getBasename();

        header("Pragma: ");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: $attach filename=\"$basename\"");
        header("Content-Length: " . $this->filesystemProxy->fileSize($attachment->getStorage()));
        fpassthru($this->filesystemProxy->readStream($attachment->getStorage()));

        exit;
    }

    /**
     * Prepare pastoral record attachments.
     *
     * @param RequestValues $post POST request vars
     * @return Collection
     * @throws MapperException
     */
    private function prepareAttachments(RequestValues $post): Collection
    {
        $newAttachments = $this->pastoralAttachmentMapper->createCollection();

        foreach ([true, false] as $isPrivate) {
            $attachzone    = $isPrivate ? self::ATTACHZONE_PRIVATE : self::ATTACHZONE_PUBLIC;
            $processedHash = [];

            // If there are any new attachments, compare and replace
            if (is_array($post[$attachzone . '_attachments'] ?? null)) {
                $collectorFactory  = new CollectorFactory();
                $attachmentObjects = $collectorFactory->build($post[$attachzone . '_attachments'])->getAll();

                /** @var Storage $storageObject */
                foreach ($attachmentObjects as $storageObject) {
                    if (in_array($storageObject->getHash(), $processedHash)) {
                        continue;
                    }
                    // Only add attachments which don't already exist
                    $attachment = $this->pastoralAttachmentMapper->createModel()
                                                                 ->setRealname($storageObject->getFilename())
                                                                 ->setStorage($storageObject)
                                                                 ->setPrivate($isPrivate);
                    $newAttachments->add($attachment);
                    $processedHash[] = $storageObject->getHash();
                }
            }
        }

        return $newAttachments;
    }

    /**
     * Returns recordActions from post vars, indexed by luAction id
     * @param RequestValues $post
     * @return Collection
     * @throws MapperException
     */
    private function prepareRecordActions(RequestValues $post): Collection
    {
        $recordActions = $this->pastoralRecordActionMapper->createCollection();
        if (!$this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            return $recordActions;
        }

        $actions       =
            is_array($post->get('actions'))
                ? $this->pastoralActionMapper->findByColumn('id', array_map('intval', array_filter($post->get('actions'))))
                : $this->pastoralActionMapper->createCollection();
        foreach ($actions as $action) {
            /** @var RecordAction\Model $recordAction */
            $recordAction = $this->pastoralRecordActionMapper->createModel();
            $recordActions->offsetSet($action->getId(), $recordAction->setAction($action));
        }
        return $recordActions;
    }

    /**
     * Get a list of available actions
     *
     * @api   GET /pastoral/actions
     * @since 21.1
     */
    public function getActions()
    {
        // If subtype id is provided, attempt to get actions of the subtype
        if (is_numeric($this->request->query->get('subtypeId'))) {
            try {
                $subtype = $this->pastoralSubTypeMapper->findById((int)$this->request->query->get('subtypeId'));
                $actions = $this->pastoralActionMapper->findForSubtype($subtype);
            } catch (MapperException $e) {
            }
        }

        // If type id is provided, attempt to get actions of the type
        if (!isset($actions) && is_numeric($this->request->query->get('typeId'))) {
            try {
                $type    = $this->pastoralTypeMapper->findById((int)$this->request->query->get('typeId'));
                $actions = $this->pastoralActionMapper->findForType($type);
            } catch (MapperException $e) {
            }
        }

        // If no actions are set for type/subtype, return empty collection
        if (!isset($actions) && !isset($type) && !isset($subtype)) {
            $actions = $this->pastoralActionMapper->findAll();
        } elseif (!isset($actions)) {
            $actions = $this->pastoralActionMapper->createCollection();
        }

        $output = [];
        /** @var Action\Model $action */
        foreach ($actions as $action) {
            $output[] = [
                'id'   => $action->getId(),
                'name' => $action->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Update action completion for this record
     *
     * @api   POST /record/{recordId:\d+}/action/update
     * @since 21.1
     */
    public function postActionUpdate(): void
    {
        $recordActionId = (int)$this->request->post->get('recordActionId');
        $checked        = (bool)$this->request->post->get('checked');

        $data = [];
        try {
            $recordAction = $this->pastoralRecordActionMapper->findById($recordActionId);
            $recordAction->setCompleted($checked)
                         ->setUpdatedBy($this->getUser());
            $this->pastoralRecordActionMapper->save($recordAction);
        } catch (MapperException $e) {
            $data['error'] = $e->getMessage();
            $this->getResponse()->status->setCode(400);
        }
        $this->getResponse()->content->set($this->buildJsonOutput($data));
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

<?php
/**
 * Controller for pastoral care
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 */

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\Category\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\Category\Model as PastoralCategoryModel;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverityModel;
use Schoolbox\Pastoral\Tags\Mapper as PastoralTagsMapper;
use Schoolbox\Pastoral\Tags\Model as PastoralTagsModel;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Pastoral\UploadQueue\Model as PastoralUploadQueueModel;

class Controller extends Base
{
    /**
     * @var PastoralMapper
     */
    protected $pastoralMapper;

    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var PastoralCategoryMapper
     */
    protected $pastoralCategoryMapper;

    /**
     * @var PastoralTypeMapper
     */
    protected $pastoralTypeMapper;

    /**
     * @var PastoralUploadQueueMapper
     */
    protected $pastoralUploadQueueMapper;

    /**
     * @var PastoralSeverityMapper
     */
    protected $pastoralSeverityMapper;

    /**
     * @var PastoralTagsMapper
     */
    protected $pastoralTagsMapper;

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var PastoralCommentMapper
     */
    protected $pastoralCommentMapper;

    /**
     * @var PastoralAccessMapper
     */
    protected $pastoralAccessMapper;

    /**
     * @var PastoralAttachmentMapper
     */
    protected $pastoralAttachmentMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var FileMapper
     */
    protected $fileMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        // Grab instances of all the mappers

        $this->setFileMapper(FileMapper::getInstance());
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setPastoralCategoryMapper(PastoralCategoryMapper::getInstance());
        $this->setPastoralTypeMapper(PastoralTypeMapper::getInstance());
        $this->setPastoralUploadQueueMapper(PastoralUploadQueueMapper::getInstance());
        $this->setPastoralSeverityMapper(PastoralSeverityMapper::getInstance());
        $this->setPastoralTagsMapper(PastoralTagsMapper::getInstance());
        $this->setPastoralCommentMapper(PastoralCommentMapper::getInstance());
        $this->setPastoralAttachmentMapper(PastoralAttachmentMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Insert an attach zone into the template
     *
     * @param Collection|PastoralAttachmentModel $attachments
     * @return array
     */
    private function getAttachZone($attachments = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload Image')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(true);

        if ($attachments != null)
            foreach ($attachments as $attachment)
                $zone->attachFiles(array($attachment->getHash()));

        return $zone->getTwigContext();
    }

    /**
     * Calculate age of student
     *
     * @param int $timestamp
     * @param int $now
     * @return int
     */
    private function calculateAge($timestamp = 0, $now = 0) {

        if ($now == 0)
            $now = time();

        $yearDiff   = date("Y", $now) - date("Y", $timestamp);
        $monthDiff  = date("m", $now) - date("m", $timestamp);
        $dayDiff    = date("d", $now) - date("d", $timestamp);

        if ($monthDiff < 0)
            $yearDiff--;
        elseif (($monthDiff == 0) && ($dayDiff < 0))
            $yearDiff--;

        $result = intval($yearDiff);

        return $result;
    }

    /**
     * Generate a pastoral report with the relevant filtering applied, this version has the accordion and pagination
     */
    public function getReport()
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        $records = $this->getReportData();

        // Restrict results based on pagination

        if (!isset($this->getRequest()->query["pageCurrent"]))
            $pageCurrent = 1;
        else
            $pageCurrent = $this->getRequest()->query["pageCurrent"];

        // This is the number of pages the user is allowed to select from at any one time (Must be even)

        $pagesToDisplay = 8;

        // This is the number of records before we force them to browse to another page

        $resultsPerPage = 15;
        $totalPages = ceil($records->count() / $resultsPerPage);

        // Put some limit checks on page numbers

        if ($pageCurrent < 1)
            $pageCurrent = 1;
        else if ($pageCurrent > $totalPages)
            $pageCurrent = $totalPages;

        // Grab the records we're going to render

        $paginatedRecords = new Collection;

        for ($i = ($pageCurrent - 1) * $resultsPerPage; $i < ($pageCurrent - 1) * $resultsPerPage + $resultsPerPage; $i++)
            if ($records[$i] != null)
                $paginatedRecords[] = $records[$i];

        // And spit this thing out
        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/report.twig',
                'data'     => array(
                    'session'               => $_SESSION,
                    'results'               => $paginatedRecords,
                    'resultsPerPage'        => $resultsPerPage,
                    'pagesToDisplay'  => $pagesToDisplay % 2 == 0 ? $pagesToDisplay : $pagesToDisplay + 1,
                    'totalPages'            => $totalPages,
                    'totalResults'          => $records->count(),
                    'pageCurrent'           => $pageCurrent,
                    'students'              => $this->getRequest()->query["students"],
                    'authors'               => $this->getRequest()->query["authors"],
                    'severity'              => $this->getRequest()->query["severity"],
                    'types'                 => $this->getRequest()->query["types"],
                    'tags'                  => $this->getRequest()->query["tags"],
                    'before'                => $this->getRequest()->query["before"],
                    'after'                 => $this->getRequest()->query["after"],
                ),
            )
        );
    }

    /**
     * Generate a printable report layout without the accordion or pagination for printing purposes
     */
    public function getReportPrint()
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Kick them back to the kerb if they aren't allowed to view this page

        if (!$this->getUser()->getRole()->isStaff())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/reportPrint.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'results'           => $this->getReportData(),
                    'students'          => $this->getRequest()->query["students"],
                    'authors'           => $this->getRequest()->query["authors"],
                    'severity'          => $this->getRequest()->query["severity"],
                    'types'             => $this->getRequest()->query["types"],
                    'tags'              => $this->getRequest()->query["tags"],
                    'before'            => $this->getRequest()->query["before"],
                    'after'             => $this->getRequest()->query["after"],
                ),
            )
        );
    }

    /**
     * Get pastoral report records
     *
     * @return Collection|Model
     */
    private function getReportData()
    {
        // Make sure all the criteria exist before we starting trying to use them, and if they don't get passed
        // across, just set blank defaults

        if (!isset($this->getRequest()->query["students"]))
            $students = '';
        else
            $students = $this->getRequest()->query["students"];

        if (!isset($this->getRequest()->query["authors"]))
            $authors = '';
        else
            $authors = $this->getRequest()->query["authors"];

        if (!isset($this->getRequest()->query["types"]))
            $types = '';
        else
            $types = $this->getRequest()->query["types"];

        if (!isset($this->getRequest()->query["severity"]))
            $severity = '';
        else
            $severity = $this->getRequest()->query["severity"];

        if (!isset($this->getRequest()->query["before"]))
            $before_date = '';
        else
            $before_date = $this->getRequest()->query["before"];

        if (!isset($this->getRequest()->query["after"]))
            $after_date = '';
        else
            $after_date = $this->getRequest()->query["after"];

        if (!isset($this->getRequest()->query["tags"]))
            $tags = '';
        else
            $tags = $this->getRequest()->query["tags"];

        // Get matching records

        return $this->getPastoralMapper()->findAllReport($before_date, $after_date, $severity, $types, $authors, $students, $tags);
    }

    /**
     * Display report builder page
     */
    public function getReportBuilder()
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get current users role. The screen is restricted to pastoral moderators only
        // Kick them back to the kerb if they shouldn't be looking at this page

        if (!$this->getUser()->getRole()->isStaff())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Create some arrays used to repopulate the selectizes

        // Students

        $studentsSelected = array();
        $studentsArray = array_filter(explode(",", $this->getRequest()->query["students"]));

        foreach ($studentsArray as $student) {
            $student = $this->getUserMapper()->findById($student);
            $studentsSelected[] = [
                'id' => $student->getId(),
                'name' => $student->getFirstname() . " " . $student->getLastname() .
                    " - Year " . $student->getYear() . " " .$student->getRole()->getName() .
                    " (" . $student->getExternalId() . ")"
            ];
        }

        // Authors

        $authorsSelected = array();
        $authorsArray = array_filter(explode(",", $this->getRequest()->query["authors"]));

        foreach ($authorsArray as $author) {
            $author = $this->getUserMapper()->findById($author);
            $authorsSelected[] = [
                'id' => $author->getId(),
                'name' => $author->getFirstname() . " " . $author->getLastname() .
                    " - " . $author->getRole()->getName() .
                    " (" . $author->getExternalId() . ")"
            ];
        }

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/reportBuilder.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'types'             => $this->getPastoralTypeMapper()->findAll()->sortBy('name', 'asc'),
                    'severities'        => $this->getPastoralSeverityMapper()->findAll()->sortBy('order', 'asc'),
                    'tags'              => $this->getPastoralTagsMapper()->findAll(),
                    'studentsSelected'  => $studentsSelected,
                    'authorsSelected'   => $authorsSelected,
                    'severitySelected'  => $this->getRequest()->query["severity"],
                    'typesSelected'     => array_filter(explode(",", $this->getRequest()->query["types"])),
                    'tagsSelected'      => array_filter(explode(",", $this->getRequest()->query["tags"])),
                    'beforeSelected'    => $this->getRequest()->query["before"],
                    'afterSelected'     => $this->getRequest()->query["after"],
                ),
            )
        );
    }

    /**
     * Returns the logged in users pastoral care moderator level
     *
     * @return int
     * @throws MapperException
     */
    public function getPastoralModeratorLevel() {
        $pastoralModeratorList = $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL);

        //Get first from collection
        if($pastoralModeratorList->count() > 0) {
            $pastoralModerator = $pastoralModeratorList->first()->getAccessLevel();
        } else {
            $pastoralModerator = UserAccess::ACCESS_DENY;
        }

        if ($this->getUser()->isSuperuser())
            $pastoralModerator = UserAccess::ACCESS_FULL;

        return $pastoralModerator;
    }

    /**
     * Display list of all pastoral records for selected student
     *
     * @param $id
     * @throws SchoolboxException
     * @throws MapperException
     */
    public function getIndex($id)
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the students parents

        $externalUserMapper = new \External_User();
        $student = $this->getUserMapper()->findWithSubjects($id);
        $student->loadParents();
        $student->loadTeachers();

        // The list screen is restricted to parents and staff

        if ($this->getPastoralModeratorLevel() < UserAccess::ACCESS_READ)
            if (!$this->getUser()->getRole()->isStaff() && !$this->getUser()->isParentOf($student))
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        if ($this->getUser()->getRole()->isParent() && !$this->getDi()->get('config')->get('pastoral_parent_access'))
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the students contact details for display

        $studentContacts = [];
        $contactList = $externalUserMapper->getStudentContactList($student->getExternalId());
        $studentExternal = $externalUserMapper->getStudent($student->getExternalId());

        if ($contactList instanceof Collection)
        {
            foreach ($contactList as $contact)
            {
                $main = null;
                try {
                    $main = $this->getUserMapper()->findByExternalId($contact['StudentContactID']);
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }
                $spouse = null;
                try {
                    $spouse = $this->getUserMapper()->findByExternalId($contact['StudentContactSpouseID']);
                } catch (MapperException $e) {
                }

                $contact['main'] = $main;
                $contact['spouse'] = $spouse;
                $studentContacts[] = $contact;
            }
        }

        // And render this mess out

        try {
            $records = $this->getPastoralMapper()->findByStudentId($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/index.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'records'           => $records,
                    'student'           => $student,
                    'studentAge'        => $this->calculateAge($studentExternal['StudentBirthDate']),
                    'studentContacts'   => $studentContacts,
                    'role'              => $this->getUser()->getRole()->getType(),
                    'types'             => $this->getPastoralTypeMapper()->findAll()->sortBy('name', 'asc'),
                    'teachers'          => $student->getTeacherCollection(),
                    'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                    'READ_ACCESS'       => UserAccess::ACCESS_READ,
                    'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                    'pastoralModerator' => $this->getPastoralModeratorLevel()
                ),
            )
        );
    }

    /**
     * Get an individual pastoral care record
     *
     * @param $studentId
     * @param $id
     * @throws SchoolboxException
     * @throws MapperException
     */
    public function getRecord($studentId, $id)
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the students parents

        $student = $this->getUserMapper()->findWithSubjects($studentId);
        $student->loadParents();

        // Get pastoral moderator status
        if ($this->getPastoralModeratorLevel() < UserAccess::ACCESS_READ) {
            if (!$this->getUser()->getRole()->isStaff() && !$this->getUser()->isParentOf($student)) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_RECORD);
            }
        }

        if ($this->getUser()->getRole()->isParent() && !$this->getDi()->get('config')->get('pastoral_parent_access'))
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the pastoral care record

        $pastoral = $this->getPastoralMapper()->findById($id);

        if ($pastoral->getPrivate()) {
            if (!$this->getPastoralMapper()->hasPrivatePermission($pastoral)) {

                // Didn't have access to private record, but did have access by role

                $this->getResponse()->redirect->afterPost("/pastoral/{$student->getId()}");
                return;
            }
        }

        // Load in related records

        $collection = new Collection();

        if (!empty($pastoral->getParent()->getId())) {
            $collection->merge($this->getPastoralMapper()->findByParentId($pastoral->getParent()->getId()));
            $collection->add($this->getPastoralMapper()->findById($pastoral->getParent()->getId()));
        } else {
            $collection->merge($this->getPastoralMapper()->findByParentId($pastoral->getId()));
            $collection->add($pastoral);
        }

        $pastoral->setRelatedRecords($collection);

        // Lets render this thing

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/record.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'record'            => $pastoral,
                    'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                    'READ_ACCESS'       => UserAccess::ACCESS_READ,
                    'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                    'pastoralModerator' => $this->getPastoralModeratorLevel(),
                    'role'              => $this->getUser()->getRole()->getType(),
                ),
            )
        );
    }

    /**
     * Insert pastoral care record page
     *
     * @param $id
     * @throws SchoolboxException
     */
    public function getInsert($id)
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Only staff should be inserting comments

        if (!$this->getUser()->getRole()->isStaff())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        try {
            $student = $this->getUserMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
        }

        // Render this thing...

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'student'           => $student,
                    'id'                => '',
                    'record'            => null,
                    'allowParents'      => $this->getDi()->get('config')->get('pastoral_parent_access'),
                    'types'             => $this->getPastoralTypeMapper()->findAll()->sortBy('name', 'asc'),
                    'categories'        => $this->getPastoralCategoryMapper()->findAll()->sortBy('name', 'asc'),
                    'severities'        => $this->getPastoralSeverityMapper()->findAll()->sortBy('order', 'asc'),
                    'roles'             => $this->getRoleMapper()->findAll(),
                    'attachzone'        => $this->getAttachZone(),
                ),
            )
        );
    }

    /**
     * Update pastoral care record page
     *
     * @param $id
     * @throws SchoolboxException
     */
    public function getUpdate($id)
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the record we are updating

        try {
            $record = $this->getPastoralMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        // Only the staff member that owns the record should be updating it

        if ($this->getPastoralModeratorLevel() < UserAccess::ACCESS_WRITE)
            if (!$this->getUser()->getRole()->isStaff() || $this->getUser()->getId() != $record->getOwner()->getId())
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_RECORD);

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'record'            => $record,
                    'allowParents'      => $this->getDi()->get('config')->get('pastoral_parent_access'),
                    'types'             => $this->getPastoralTypeMapper()->findAll()->sortBy('name', 'asc'),
                    'categories'        => $this->getPastoralCategoryMapper()->findAll()->sortBy('name', 'asc'),
                    'severities'        => $this->getPastoralSeverityMapper()->findAll()->sortBy('order', 'asc'),
                    'roles'             => $this->getRoleMapper()->findAll(),
                    'WRITE_ACCESS'      => WRITE_ACCESS,
                    'READ_ACCESS'       => READ_ACCESS,
                    'FULL_ACCESS'       => FULL_ACCESS,
                    'pastoralModerator' => $this->getPastoralModeratorLevel(),
                    'attachzone'        => $this->getAttachZone($this->getPastoralAttachmentMapper()->findByPastoralId($id)),
                ),
            )
        );
    }

    /**
     * Delete a comment
     *
     * @throws SchoolboxException
     */
    public function postCommentDelete()
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the comment we are deleting

        try {
            /** @var PastoralCommentModel $comment */
            $comment = $this->getPastoralCommentMapper()->findOneByColumn("id", $this->getRequest()->post['id']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        // Only the staff member that owns the record should be updating it

        if (!$this->getUser()->getRole()->isStaff() || $this->getUser()->getId() != $comment->getOwner()->getId())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_RECORD);

        $pastoralExternal = new \External_Pastoral();
        $pastoralExternal->deleteComment($comment);

        $this->getPastoralCommentMapper()->delete($comment);
    }

    /**
     * Post a comment
     *
     * @throws SchoolboxException
     */
    public function postComment()
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Post a comment on a pastoral record
        // Only staff should be doing this

        if (!$this->getUser()->getRole()->isStaff())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // No blank comments thanks

        if (trim($this->getRequest()->post["body"]) != '')
        {
            $comment = new PastoralCommentModel();
            $comment->setPastoralId($this->getRequest()->post["pastoral_id"]);
            $comment->setBody($this->getRequest()->post["body"]);
            $comment->setOwner($this->getUser());
            $comment->setCreatedAt(\Alaress_Date_Format::now());
            $this->getPastoralCommentMapper()->setSessionData($_SESSION);
            $this->getPastoralCommentMapper()->save($comment);

            $pastoralExternal = new \External_Pastoral();
            $pastoralExternal->createComment($comment);
        }

        // Go back to pastoral record

        $this->getResponse()->redirect->afterPost("/pastoral/{$this->getRequest()->post["student_id"]}/{$this->getRequest()->post["pastoral_id"]}");
    }

    /**
     * Insert/update a pastoral care record
     *
     * @throws SchoolboxException
     */
    public function postSave()
    {
        $post = $this->getRequest()->post;

        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Only staff should be doing this

        if (!$this->getUser()->getRole()->isStaff()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);
        }

        // External interface

        $pastoralExternal = new \External_Pastoral();

        // Keep track of IDs created

        /** @var Collection|Model $records */
        $records = new Collection();

        if (empty($this->getRequest()->post["id"])) {

            // Creating new pastoral record

            // Make an array of all students this record needs to be created on

            $students = array();

            // The student the user was currently viewing

            $students[$this->getRequest()->post["student_id"]] = 1;

            // And any other students specified on the pastoral record

            if (is_array($this->getRequest()->post["duplicate_id"])) {
                foreach ($this->getRequest()->post["duplicate_id"] as $studentId) {
                    if (trim($studentId) != '') {
                        $students[$studentId] = 1;
                    }
                }
            }

            // Create a post date, so they are all the same

            $createdAt = \Alaress_Date_Format::now();

            // Go through every kid

            foreach ($students as $student_id => $value) {

                $record = new PastoralModel();

                // If this is not the first record, set the parent ID

                if (isset($parent)) {
                    $record->setParent($parent);
                }

                try {
                    /** @var User $student */
                    $student = $this->getUserMapper()->findById($student_id);
                } catch (\Exception $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                }

                try {
                    /** @var PastoralTypeModel $type */
                    $type = $this->getPastoralTypeMapper()->findById($this->getRequest()->post["typeId"]);

                    try {
                        /** @var PastoralCategoryModel $category */
                        $category = $this->getPastoralCategoryMapper()->findById($this->getRequest()->post["categoryId"]);
                        $record->setCategory($category);
                    } catch (MapperException $e) {
                        // Noop, this can be blank
                    }

                    /** @var PastoralSeverityModel $severity */
                    $severity = $this->getPastoralSeverityMapper()->findById($this->getRequest()->post["severityId"]);
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                }

                $record->setStudent($student);
                $record->setHeading($this->getRequest()->post["heading"]);
                $record->setType($type);
                $record->setSeverity($severity);
                $record->setBody($this->getRequest()->post["body"]);
                $record->setPrivate($this->getRequest()->post["private"]);
                $record->setOwner($this->getUser());
                $record->setCreatedAt($createdAt);
                $this->getPastoralMapper()->setSessionData($_SESSION);
                $this->getPastoralMapper()->save($record);

                $records->add($record);

                // Store the first records ID for use as parent for subsequent copies

                if (!isset($parent)) {
                    $parent = $record;
                }

                // Save the authorised users

                if (isset($this->getRequest()->post["user_id"])) {
                    if (is_array($this->getRequest()->post["user_id"])) {
                        foreach ($this->getRequest()->post["user_id"] as $user_id) {

                            try {
                                $user = $this->getUserMapper()->findById($user_id);
                            } catch (MapperException $e) {
                                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                            }

                            $access = new PastoralAccessModel();
                            $access->setPastoralId($record->getId());
                            $access->setUser($user);
                            $this->getPastoralAccessMapper()->save($access);
                        }
                    }
                }

                // Save the tags

                $tags = explode(",", $this->getRequest()->post["tags"]);

                foreach ($tags as $tagCurrent) {
                    if (trim($tagCurrent) != '') {
                        $tag = new PastoralTagsModel();
                        $tag->setName($tagCurrent);
                        $tag->setPastoralId($record->getId());
                        $this->getPastoralTagsMapper()->save($tag);
                    }
                }

                // Save the audiences

                foreach ($this->getRequest()->post["role"] as $role_id => $checked) {
                    if ($checked) {
                        try {
                            /** @var \Schoolbox\Role $role */
                            $role = $this->getRoleMapper()->findById($role_id);
                        } catch (MapperException $e) {
                            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                        }

                        $access = new PastoralAccessModel();
                        $access->setPastoralId($record->getId());
                        $access->setRole($role);
                        $this->getPastoralAccessMapper()->save($access);
                    }
                }

                $pastoralExternal->createPastoral($record);
            }
        }
        else
        {
            // Modifying existing record

            try {
                $record = $this->getPastoralMapper()->findById($this->getRequest()->post["id"]);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }

            $records->add($record);

            // Only the owner or a moderator should be updating a record

            if ($this->getPastoralModeratorLevel() < UserAccess::ACCESS_WRITE) {
                if ($this->getUser()->getId() != $record->getOwner()->getId()) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_RECORD);
                }
            }

            try {
                /** @var PastoralTypeModel $type */
                $type = $this->getPastoralTypeMapper()->findById($this->getRequest()->post["typeId"]);
                /** @var PastoralCategoryModel $category */
                $category = $this->getPastoralCategoryMapper()->findById($this->getRequest()->post["categoryId"]);
                /** @var PastoralSeverityModel $severity */
                $severity = $this->getPastoralSeverityMapper()->findById($this->getRequest()->post["severityId"]);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }

            $record->setHeading($this->getRequest()->post["heading"]);
            $record->setType($type);
            $record->setCategory($category);
            $record->setSeverity($severity);
            $record->setBody($this->getRequest()->post["body"]);
            $record->setPrivate($this->getRequest()->post["private"]);
            $record->setUpdatedAt(\Alaress_Date_Format::now());
            $this->getPastoralMapper()->setSessionData($_SESSION);
            $this->getPastoralMapper()->save($record);

            // Delete old tags

            $this->getPastoralTagsMapper()->deleteByColumn("pastoral_id", $record->getId());

            // Save the tags

            $tags = explode(",", $this->getRequest()->post["tags"]);

            foreach ($tags as $tagCurrent) {
                if (trim($tagCurrent) != '') {
                    $tag = new PastoralTagsModel();
                    $tag->setName($tagCurrent);
                    $tag->setPastoralId($record->getId());
                    $this->getPastoralTagsMapper()->save($tag);
                }
            }

            // Delete user permissions

            $accessRoles = $record->getAccessList();

            $deleteIds = [];

            if (trim($this->getRequest()->post["deleteIds"]) != "") {
                $deleteIds = explode(",", $this->getRequest()->post["deleteIds"]);

                foreach ($deleteIds as $user_id) {
                    foreach ($accessRoles as $role) {
                        if ($role->getUser()->getId() == $user_id) {
                            $this->getPastoralAccessMapper()->delete($role);
                            break;
                        }
                    }
                }
            }

            // Update the authorised users

            if (isset($this->getRequest()->post["user_id"])) {
                if (is_array($this->getRequest()->post["user_id"])) {
                    foreach ($this->getRequest()->post["user_id"] as $user_id) {

                        $found = false;

                        // Don't re-add deleted users

                        foreach ($accessRoles as $role) {
                            if ($role->getUser()->getId() == $user_id) {
                                if (in_array($user_id, $deleteIds)) {
                                    $found = true;
                                    break;
                                }
                            }
                        }

                        if (!$found) {

                            try {
                                $user = $this->getUserMapper()->findById($user_id);
                            } catch (MapperException $e) {
                                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_USER_NOT_FOUND);
                            }

                            $access = new PastoralAccessModel();
                            $access->setPastoralId($record->getId());
                            $access->setUser($user);
                            $this->getPastoralAccessMapper()->save($access);
                        }
                    }
                }
            }

            // Update the role based audience

            foreach ($this->getRequest()->post["role"] as $role_id => $checked) {

                if ($checked) {

                    // Add this access if it doesn't already exist

                    $found = false;

                    foreach ($accessRoles as $role) {
                        if ($role->getRole()->getId() == $role_id) {
                            $found = true;
                            break;
                        }
                    }


                    try {
                        /** @var \Schoolbox\Role $role */
                        $role = $this->getRoleMapper()->findById($role_id);
                    } catch (MapperException $e) {
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
                    }

                    if (!$found) {
                        $access = new PastoralAccessModel();
                        $access->setPastoralId($record->getId());
                        $access->setRole($role);
                        $this->getPastoralAccessMapper()->save($access);
                    }

                } else {

                    // Delete this access if it exists because its no longer ticked

                    $access = $this->getPastoralAccessMapper()->findByColumnList([
                            'pastoral_id' => $record->getId(),
                            'role_id' => $role_id
                        ]
                    );

                    $this->getPastoralAccessMapper()->delete($access);
                }
            }

            // Update external record

            if ($record->getExternalId() != "")
                $pastoralExternal->updatePastoral($record);
            else
                $pastoralExternal->createPastoral($record);
        }

        /** @var PastoralModel $record */
        foreach ($records as $record)
        {
            // Delete attachments if required

            // Keep track of attachments that aren't deleted so we don't add them again

            $retainedAttachments = array();

            // Get all attachments associated with this record

            try {
                $attachments = $this->getPastoralAttachmentMapper()->findByPastoralId($record->getId());
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
            }

            foreach ($attachments as $attachment) {

                $foundAttachment = false;

                if (is_array($post['attachment'])) {
                    $attachmentObjects = (new CollectorFactory())->build($post['attachment'])->getAll();

                    /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                    foreach ($attachmentObjects as $storageObject) {

                        if ($attachment->getHash() == $storageObject->getHash()) {
                            $foundAttachment = true;
                            break;
                        }
                    }
                }

                if (!$foundAttachment) {

                    // The attachment was found in the database, remove it

                    $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                    $this->getPastoralAttachmentMapper()->delete($attachment);

                } else {

                    // We are keeping this attachment

                    $retainedAttachments[] = $attachment->getHash();
                }
            }

            // Save any new attachments

            if (is_array($post['attachment'])) {
                $attachmentObjects = (new CollectorFactory())->build($post['attachment'])->getAll();

                /** @var \Schoolbox\Resource\Storage\Model $storageObject */
                foreach ($attachmentObjects as $storageObject) {
                    // Only add attachments which don't already exist

                    if (!in_array($storageObject->getHash(), $retainedAttachments)) {

                        if ($this->getDi()->get('config')->get("external_pastoral_upload")) {
                            // Queue file for upload to Synergetic DocMan
                            $queue = new PastoralUploadQueueModel();
                            $queue->setPastoralId($record->getId());
                            $queue->setExternalId($this->getUser()->getExternalId());
                            $queue->setStudentExternalId($record->getStudent()->getExternalId());
                            $queue->setHash($storageObject->getHash());
                            $queue->setStatus(PastoralUploadQueueMapper::UPLOAD_STATUS_PENDING);
                            $queue->setType($storageObject->getType());
                            $queue->setCreatedAt(\Alaress_Date_Format::now());
                            $queue->setUpdatedAt(\Alaress_Date_Format::now());
                            $this->getPastoralUploadQueueMapper()->save($queue);
                        }

                        $attachment = new PastoralAttachmentModel();
                        $attachment->setPastoralId($record->getId());
                        $attachment->setHash($storageObject->getHash());
                        $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                        $this->getPastoralAttachmentMapper()->save($attachment);
                    }
                }
            }
        }

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost("/pastoral/{$this->getRequest()->post["student_id"]}");
    }

    /**
     * Delete a pastoral care record
     *
     * @throws SchoolboxException
     */
    public function postDelete()
    {
        if ($this->getUser()->getRole()->isStudent())
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_MODULE);

        // Get the record we are deleting

        try {
            $record = $this->getPastoralMapper()->findById($this->getRequest()->post["id"]);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_NOT_FOUND);
        }

        // Work out if this person should be allowed to delete the record
        if ($this->getPastoralModeratorLevel() < UserAccess::ACCESS_WRITE) {
            if (!$this->getUser()->getRole()->isStaff() || $this->getUser()->getId() != $record->getOwner()->getId()) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PASTORAL_PERM_RECORD);
            }
        }

        $this->getPastoralCommentMapper()->deleteByColumn("pastoral_id", $this->getRequest()->post["id"]);

        // Delete on external database

        $pastoralExternal = new \External_Pastoral();
        $pastoralExternal->deletePastoral($record);

        // Delete locally

        $this->getPastoralMapper()->delete($record);
    }

    /**
     * Here be the getters/setters, nothing else interesting to see
     */

    /**
     * @param $pastoralMapper
     */
    public function setPastoralMapper($pastoralMapper) {
        $this->pastoralMapper = $pastoralMapper;
    }

    /**
     * @return PastoralMapper
     */
    public function getPastoralMapper() {
        return $this->pastoralMapper;
    }

    /**
     * @param $userAccessMapper
     */
    public function setUserAccessMapper($userAccessMapper) {
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper() {
        return $this->userAccessMapper;
    }

    /**
     * @param $pastoralTypeMapper
     */
    public function setPastoralTypeMapper($pastoralTypeMapper) {
        $this->pastoralTypeMapper = $pastoralTypeMapper;
    }

    /**
     * @return PastoralTypeMapper
     */
    public function getPastoralTypeMapper() {
        return $this->pastoralTypeMapper;
    }

    /**
     * @param $pastoralUploadQueueMapper
     */
    public function setPastoralUploadQueueMapper($pastoralUploadQueueMapper) {
        $this->pastoralUploadQueueMapper = $pastoralUploadQueueMapper;
    }

    /**
     * @return PastoralUploadQueueMapper
     */
    public function getPastoralUploadQueueMapper() {
        return $this->pastoralUploadQueueMapper;
    }    

    /**
     * @param $pastoralCategoryMapper
     */
    public function setPastoralCategoryMapper($pastoralCategoryMapper) {
        $this->pastoralCategoryMapper = $pastoralCategoryMapper;
    }

    /**
     * @return PastoralCategoryMapper
     */
    public function getPastoralCategoryMapper() {
        return $this->pastoralCategoryMapper;
    }    

    /**
     * @param $pastoralSeverityMapper
     */
    public function setPastoralSeverityMapper($pastoralSeverityMapper) {
        $this->pastoralSeverityMapper = $pastoralSeverityMapper;
    }

    /**
     * @return PastoralSeverityMapper
     */
    public function getPastoralSeverityMapper() {
        return $this->pastoralSeverityMapper;
    }

    /**
     * @param $pastoralTagsMapper
     */
    public function setPastoralTagsMapper($pastoralTagsMapper) {
        $this->pastoralTagsMapper = $pastoralTagsMapper;
    }

    /**
     * @return PastoralTagsMapper
     */
    public function getPastoralTagsMapper() {
        return $this->pastoralTagsMapper;
    }

    /**
     * @param $roleMapper
     */
    public function setRoleMapper($roleMapper) {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper() {
        return $this->roleMapper;
    }

    /**
     * @param $userMapper
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param $pastoralCommentMapper
     */
    public function setPastoralCommentMapper($pastoralCommentMapper) {
        $this->pastoralCommentMapper = $pastoralCommentMapper;
    }

    /**
     * @return PastoralCommentMapper
     */
    public function getPastoralCommentMapper() {
        return $this->pastoralCommentMapper;
    }

    /**
     * @param $pastoralAccessMapper
     */
    public function setPastoralAccessMapper($pastoralAccessMapper) {
        $this->pastoralAccessMapper = $pastoralAccessMapper;
    }

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper() {
        return $this->pastoralAccessMapper;
    }

    /**
     * @param $pastoralAttachmentMapper
     */
    public function setPastoralAttachmentMapper($pastoralAttachmentMapper) {
        $this->pastoralAttachmentMapper = $pastoralAttachmentMapper;
    }

    /**
     * @return PastoralAttachmentMapper
     */
    public function getPastoralAttachmentMapper() {
        return $this->pastoralAttachmentMapper;
    }

    /**
     * @param $errorMapper
     */
    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper() {
        return $this->errorMapper;
    }

    /**
     * @param $fileMapper
     */
    public function setFileMapper($fileMapper) {
        $this->fileMapper = $fileMapper;
    }

    /**
     * @return FileMapper
     */
    public function getFileMapper() {
        return $this->fileMapper;
    }
}

/*
 * This code will self-destruct in 5 seconds...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/

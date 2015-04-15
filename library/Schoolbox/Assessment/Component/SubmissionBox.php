<?php
/**
 * Model for submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;

use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Category;
use Schoolbox\Assessment\Component\SubmissionBox\File;
use Schoolbox\Assessment\Component\SubmissionBox\FileCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Notification;
use Schoolbox\Assessment\Component\SubmissionBox\NotificationCollection;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Role;
use Schoolbox\User;

class SubmissionBox implements ISoftDeletable
{
    use HasCurriculumNodes;

    const MARK_VISIBILITY_PRIVATE = 0;
    const MARK_VISIBILITY_STUDENT = 1;
    const MARK_VISIBILITY_PARENT_STUDENT = 2;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Category
     */
    protected $category;

    /**
     * @var WorkType
     */
    protected $work_type;

    /**
     * @var GradeLevel\Group\Model
     */
    protected $gradeGroup;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var Task
     */
    protected $task;

    /**
     * @var Task\Instance
     */
    protected $task_instance;

    /**
     * @var int
     */
    protected $legacy_file_id;

    /**
     * @var FileCollection
     */
    protected $file_links;

    /**
     * @var NotificationCollection
     */
    protected $notifications;

    /**
     * @var User
     */
    protected $owner;

    /**
     * If this isn't null, the submission box won't be shown on student's
     * calendars until this date.
     *
     * @var \Alaress_Date_Format
     */
    protected $publish_date;

    /**
     * @var \Alaress_Date_Format
     */
    protected $open_date;

    /**
     * @var \Alaress_Date_Format
     */
    protected $due_date;

    /**
     * @var \Alaress_Date_Format
     */
    protected $late_date;

    /**
     * @var bool
     */
    protected $accept_online;

    /**
     * @var bool
     */
    protected $accept_multiple;

    /**
     * @var \Alaress_Date_Format
     */
    protected $created;

    /**
     * @var int
     */
    protected $weight = 0;

    /**
     * @var int
     */
    protected $max_mark;

    /**
     * @var int
     */
    protected $mark_type;

    /**
     * @var int
     */
    protected $rubric_result_set;

    /**
     * @var int
     */
    protected $mark_visibility;

    /**
     * Skip entries in the People Selector when they haven't submitted
     * anything to this Due Work
     *
     * @var int
     */
    protected $skipUnsubmitted = 0;

    /**
     * @var bool
     */
    protected $deleted;

    public function __construct()
    {
        $this->setNotifications(new NotificationCollection(array()));
        $this->setFileLinks(new SubmissionBox\FileCollection(array()));
        $this->setCategory(new Category());
        $this->setWorkType(new WorkType());
        $this->setMarkType(new MarkType());
        $this->setGradeGroup(new GradeLevel\Group\Model());
        $this->setCurriculumNodes(new Collection());
    }

    /**
     * Determine whether a submissionBox is a rubric one or not
     *
     * @return bool
     */
    public function isRubric()
    {
        if ($this->getMarkType()->getId() == MarkType::TYPE_RUBRIC) {
            return true;
        }

        return false;
    }

    /**
     * @param Notification $notification
     */
    public function addNotification(Notification $notification)
    {
        $this->notifications->add($notification);
    }

    /**
     * Is this mark shown to students?
     *
     * @return bool
     */
    public function isMarkShownToStudents()
    {
        return in_array(
            $this->getMarkVisibility(),
            array(self::MARK_VISIBILITY_STUDENT, self::MARK_VISIBILITY_PARENT_STUDENT)
        );
    }

    /**
     * Is this mark shown to parents?
     *
     * @return bool
     */
    public function isMarkShownToParents()
    {
        return $this->getMarkVisibility() == self::MARK_VISIBILITY_PARENT_STUDENT;
    }

    /**
     * Is this mark shown to the given user role?
     *
     * @param Role $role
     *
     * @return bool
     */
    public function isMarkShownToRole(Role $role)
    {
        if ($role->isStaff()) {
            return true;
        } elseif ($role->isStudent() && $this->isMarkShownToStudents()) {
            return true;
        } elseif ($role->isParent() && $this->isMarkShownToParents()) {
            return true;
        }
        return false;
    }

    /**
     * Get index of gradient
     *
     * @return int
     */
    public function getGradientIndex()
    {
        $urgency = intval(($this->getDueDate()->getUnixTimestamp() - time()) / 86400);

        return max(0, min(10, $urgency));
    }

    /* Getters/Setters
     */

    /**
     * @param  Category $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param  string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int $legacy_file_id
     * @return $this
     */
    public function setLegacyFileId($legacy_file_id)
    {
        $this->legacy_file_id = $legacy_file_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getLegacyFileId()
    {
        return $this->legacy_file_id;
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  \Schoolbox\Assessment\Course\Task $task
     * @return $this
     */
    public function setTask($task)
    {
        $this->task = $task;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Course\Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param  Task\Instance $task_instance
     * @return $this
     */
    public function setTaskInstance($task_instance)
    {
        $this->task_instance = $task_instance;
        return $this;
    }

    /**
     * @return Task\Instance
     */
    public function getTaskInstance()
    {
        return $this->task_instance;
    }

    /**
     * @param  WorkType $type
     * @return $this
     */
    public function setWorkType($type)
    {
        $this->work_type = $type;
        return $this;
    }

    /**
     * @return WorkType
     */
    public function getWorkType()
    {
        return $this->work_type;
    }

    /**
     * @param  FileCollection $link
     * @return $this
     */
    public function setFileLinks($link)
    {
        $this->file_links = $link;
        return $this;
    }

    /**
     * @return FileCollection|File[]
     */
    public function getFileLinks()
    {
        return $this->file_links;
    }

    /**
     * @param File $file
     */
    public function addFileLink(File $file)
    {
        $this->file_links->add($file);
    }

    /**
     * @param  boolean $accept_multiple
     * @return $this
     */
    public function setAcceptMultiple($accept_multiple)
    {
        $this->accept_multiple = $accept_multiple;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAcceptMultiple()
    {
        return $this->accept_multiple;
    }

    /**
     * @param  boolean $accept_online
     * @return $this
     */
    public function setAcceptOnline($accept_online)
    {
        $this->accept_online = $accept_online;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAcceptOnline()
    {
        return $this->accept_online;
    }

    /**
     * @param  \Alaress_Date_Format $created
     * @return $this
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param  \Alaress_Date_Format $due_date
     * @return $this
     */
    public function setDueDate($due_date)
    {
        $this->due_date = $due_date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->due_date;
    }

    /**
     * @param  \Alaress_Date_Format $late_date
     * @return $this
     */
    public function setLateDate($late_date)
    {
        $this->late_date = $late_date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getLateDate()
    {
        return $this->late_date;
    }

    /**
     * @param  MarkType $mark_type
     * @return $this
     */
    public function setMarkType($mark_type)
    {
        $this->mark_type = $mark_type;
        return $this;
    }

    /**
     * @return MarkType
     */
    public function getMarkType()
    {
        return $this->mark_type;
    }

    /**
     * @param  int $max_mark
     * @return $this
     */
    public function setMaxMark($max_mark)
    {
        $this->max_mark = $max_mark;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxMark()
    {
        return $this->max_mark;
    }

    /**
     * @param  \Alaress_Date_Format $open_date
     * @return $this
     */
    public function setOpenDate($open_date)
    {
        $this->open_date = $open_date;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->open_date;
    }

    /**
     * @param  User $owner
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param  int $rubric_result_set
     * @return $this
     */
    public function setRubricResultSet($rubric_result_set)
    {
        $this->rubric_result_set = $rubric_result_set;
        return $this;
    }

    /**
     * @return int
     */
    public function getRubricResultSet()
    {
        return $this->rubric_result_set;
    }

    /**
     * @param  NotificationCollection $notifications
     * @return $this
     */
    public function setNotifications($notifications)
    {
        $this->notifications = $notifications;
        return $this;
    }

    /**
     * @return NotificationCollection|Notification[]
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @param  int $mark_visibility
     * @return $this
     */
    public function setMarkVisibility($mark_visibility)
    {
        $this->mark_visibility = $mark_visibility;
        return $this;
    }

    /**
     * @return int
     */
    public function getMarkVisibility()
    {
        return $this->mark_visibility;
    }

    /**
     * @param  boolean $deleted
     * @return $this
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Is this deleted? (added so there's a consistent method for determining if
     * a model is deleted)
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getDeleted();
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    /**
     * Set skip unsubmitted
     *
     * @param int $skipUnsubmitted
     */
    public function setSkipUnsubmitted($skipUnsubmitted)
    {
        $this->skipUnsubmitted = $skipUnsubmitted;
    }

    /**
     * Get skip unsubmitted
     *
     * @return int
     */
    public function getSkipUnsubmitted()
    {
        return $this->skipUnsubmitted;
    }

    /**
     * @return GradeLevel\Group\Model
     */
    public function getGradeGroup()
    {
        return $this->gradeGroup;
    }

    /**
     * @param  GradeLevel\Group\Model $gradeGroup
     * @return $this
     */
    public function setGradeGroup($gradeGroup)
    {
        $this->gradeGroup = $gradeGroup;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publish_date;
    }

    /**
     * @param  \Alaress_Date_Format $publish_date
     * @return $this
     */
    public function setPublishDate($publish_date)
    {
        $this->publish_date = $publish_date;
        return $this;
    }
}

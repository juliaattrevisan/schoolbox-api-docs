<?php
/**
 * Model for submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component;

use Alaress_Date_Format;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\Model;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Permission\Access\Access;

class SubmissionBox implements IAssessableDataModel, ISoftDeletable
{
    use HasCurriculumNodes;
    use AssessmentModelTrait;

    // Can learners give themselves feedback?
    public const SELF_REVIEWABILITY_NONE         = 'none';         // only instructors can review
    public const SELF_REVIEWABILITY_REFLECT_ONLY = 'reflect-only'; // learner can only reflect on their work, but not give grades
    public const SELF_REVIEWABILITY_FULL         = 'full';         // learner can give reflect and give grades

    /**
     * @var int
     */
    protected $id;

    /**
     * @deprecated this only works for legacy (pre 16.5) submission boxes - dependency on this value for boxes created 16.5+ will snap things
     * @var FolderModel
     */
    protected $category;


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
     * @var SubmissionBox\SingleFile\Model[]|GenericCollection
     */
    protected $attachments;

    /**
     * @var User
     */
    protected $owner;

    /**
     * If this isn't null, the submission box won't be shown on student's
     * calendars until this date.
     *
     * @var Alaress_Date_Format
     */
    protected $publish_date;

    /**
     * @var Alaress_Date_Format
     */
    protected $open_date;

    /**
     * @var Alaress_Date_Format
     */
    protected $due_date;

    /**
     * @var Alaress_Date_Format
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
     * @var Alaress_Date_Format
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
     * @return int
     */
    protected $mark_type;

    /** @var bool */
    protected $useRubric;

    /** @var bool */
    protected $markedPerCapability;

    /** @var bool */
    protected $markedUsingRange;

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
     * Plagscan (or equivalent) is opt-in on a per-assessment basis
     *
     * @var bool
     */
    private $similarityCheckEnabled;

    /**
     * @var bool
     */
    protected $deleted;

    /**
     * Can learners reflect on or grade their own submissions?
     *
     * @see SubmissionBox::SELF_REVIEWABILITY_NONE and adjacent constants
     * @var string
     */
    private $selfReviewability;


    /**
     * SubmissionBox constructor.
     */
    public function __construct()
    {
        $this->setCategory(new FolderModel());
        $this->setPublishDate(new \Alaress_Date_NullDate());
        $this->setOpenDate(new \Alaress_Date_NullDate());
        $this->setDueDate(new \Alaress_Date_NullDate());
        $this->setLateDate(new \Alaress_Date_NullDate());
        $this->setAssessmentModel(new Model());
    }

    /**
     * Determine whether a submissionBox is a rubric one or not
     *
     * @return bool
     */
    public function isRubric()
    {
        return $this->useRubric;
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
            array(IAssessable::MARK_VISIBILITY_STUDENT, IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
        );
    }

    /**
     * Is this mark shown to parents?
     *
     * @return bool
     */
    public function isMarkShownToParents()
    {
        return $this->getMarkVisibility() == IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
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
     * What is the plaintext visibility of this submission box
     *
     * @return string
     */
    public function getMarkVisibilityDescription() {
        switch($this->getMarkVisibility()) {
            case IAssessable::MARK_VISIBILITY_PRIVATE:
                return 'Staff Only';
            case IAssessable::MARK_VISIBILITY_STUDENT:
                return 'Staff and Students';
            case IAssessable::MARK_VISIBILITY_PARENT_STUDENT:
                return 'Staff, Students and Parents';
        }
        return '';
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

    function __clone()
    {
        $this->id = null;

    }

    /* Getters/Setters
     */

    /**
     * @deprecated this only works for legacy (pre 16.5) submission boxes - dependency on this value for boxes created 16.5+ will snap things
     * @param  FolderModel $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @deprecated this only works for legacy (pre 16.5) submission boxes - dependency on this value for boxes created 16.5+ will snap things
     * @return FolderModel
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param  string $description
     * @return $this
     */
    public function setDescription(string $description)
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
     * @return GenericCollection|SubmissionBox\SingleFile\Model[]
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param GenericCollection|SubmissionBox\SingleFile\Model[] $attachments
     * @return static
     */
    public function setAttachments(GenericCollection $attachments)
    {
        $attachments->assertCollectionOf(SubmissionBox\SingleFile\Model::class);
        $this->attachments = $attachments;
        return $this;
    }

    /**
     * @param SubmissionBox\SingleFile\Model $attachment
     * @return static
     */
    public function addAttachment(SubmissionBox\SingleFile\Model $attachment)
    {
        $this->attachments->add($attachment);
        return $this;
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
     * @param  Alaress_Date_Format $due_date
     * @return $this
     */
    public function setDueDate($due_date)
    {
        $this->due_date = $due_date;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->due_date;
    }

    /**
     * @param  Alaress_Date_Format $late_date
     * @return $this
     */
    public function setLateDate($late_date)
    {
        $this->late_date = $late_date;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
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
     * @param  Alaress_Date_Format $open_date
     * @return $this
     */
    public function setOpenDate($open_date)
    {
        $this->open_date = $open_date;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
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
     * @return boolean
     */
    public function getUseRubric()
    {
        return $this->isRubric();
    }


    /**
     * @param boolean $useRubric
     * @return static
     */
    public function setUseRubric($useRubric)
    {
        $this->useRubric = $useRubric;
        return $this;
    }


    /**
     * @deprecated mapper method
     * @return boolean
     */
    public function getMarkedPerCapability()
    {
        return $this->isMarkedPerCapability();
    }


    /**
     * @return bool
     */
    public function isMarkedPerCapability()
    {
        return $this->markedPerCapability;
    }


    /**
     * @param boolean $markedPerCapability
     * @return static
     */
    public function setMarkedPerCapability($markedPerCapability)
    {
        $this->markedPerCapability = $markedPerCapability;
        return $this;
    }

    /**
     * @deprecated mapper method
     * @return boolean
     */
    public function getMarkedUsingRange()
    {
        return $this->isMarkedUsingRange();
    }


    /**
     * @return bool
     */
    public function isMarkedUsingRange()
    {
        return $this->markedUsingRange;
    }


    /**
     * @param boolean $markedUsingRange
     * @return static
     */
    public function setMarkedUsingRange($markedUsingRange)
    {
        $this->markedUsingRange = $markedUsingRange;
        return $this;
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
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     * @return $this
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Set skip unsubmitted
     *
     * @param int $skipUnsubmitted
     * @return $this
     */
    public function setSkipUnsubmitted($skipUnsubmitted)
    {
        $this->skipUnsubmitted = $skipUnsubmitted;
        return $this;
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
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publish_date;
    }

    /**
     * @param  Alaress_Date_Format $publish_date
     * @return $this
     */
    public function setPublishDate($publish_date)
    {
        $this->publish_date = $publish_date;
        return $this;
    }

    /**
     * Helper method for setting dates from a form.
     *
     * @param array $formDates
     * @return $this
     */
    public function setDatesFromForm($formDates)
    {
        $publish_date = isset($formDates['publish_date'])
            ? \Alaress_Date_Format::newLocalShort($formDates['publish_date'])->startOfDay()
            : \Alaress_Date_Format::now()->startOfDay();
        $open_date    = isset($formDates['open_date'])
            ? \Alaress_Date_Format::newLocalShort($formDates['open_date'])->startOfDay()
            : $publish_date;
        $due_date     = isset($formDates['due_date'])
            ? \Alaress_Date_Format::newLocalShort($formDates['due_date'])->endOfDay()
            : \Alaress_Date_Format::now()->endOfDay();
        $late_date    = isset($formDates['late_date'])
            ? \Alaress_Date_Format::newLocalShort($formDates['late_date'])->endOfDay()
            : $due_date;
        $this->setPublishDate($publish_date);
        $this->setOpenDate($open_date);
        $this->setDueDate($due_date);
        $this->setLateDate($late_date);
        return $this;
    }


    /**
     * Returns true if the current time is between the open and late dates of this box
     *
     * @return bool
     */
    public function isOpen()
    {
        return Alaress_Date_Format::now()->between($this->getOpenDate(), $this->getLateDate());
    }

    /**
     * Determine whether a user can view the rubrics associated with
     * this submission box
     *
     * @param User $user
     * @return bool
     */
    public function canViewRubric(User $user)
    {
        // no rubric to view
        if (!$this->isRubric()) {
            return false;
        }

        switch ($user->getRole()->getType()) {
            case Role::PARENT:
                return $this->isMarkShownToParents();
            case Role::STUDENT:
                return $this->isMarkShownToStudents();
            case Role::STAFF:
                return true;
            default:
                return false;
        }
    }

    /**
     * Determine whether $user has $requiredAccessLevel (or greater)
     * upon the Folder associated with $submissionBox
     *
     * @param User $user
     * @param $requiredAccessLevel
     * @return bool
     */
    public function hasAccess(User $user, $requiredAccessLevel)
    {
        $access = Access::getInstance();

        /* Access level for $user on $submissionBox
         */
        $boxLevel = $access->getFolderAccess($user, $this->getCategory()->getId());

        if ($boxLevel >= $requiredAccessLevel) {
            return true;
        }

        return false;
    }

    /**
     * @return int the core Assessment ID
     */
    public function getAssessmentId()
    {
        return $this->getAssessmentModel()->getId();
    }


    /**
     * @return null|string
     */
    public function getSelfReviewability(): ?string
    {
        return $this->selfReviewability;
    }

    /**
     * @param string $selfReviewability
     * @return SubmissionBox
     */
    public function setSelfReviewability(string $selfReviewability): self
    {
        $this->selfReviewability = $selfReviewability;
        return $this;
    }


    /**
     * @return null|bool
     */
    public function getSimilarityCheckEnabled(): ?bool
    {
        return $this->similarityCheckEnabled;
    }


    /**
     * @param bool|null $similarityCheckEnabled
     * @return $this
     */
    public function setSimilarityCheckEnabled(?bool $similarityCheckEnabled): self
    {
        $this->similarityCheckEnabled = $similarityCheckEnabled;
        return $this;
    }


    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        // Submission box takes this value from the assessment it is *composed* of
        return $this->assessmentModel->isDeleted();
    }
}

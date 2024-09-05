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
use Schoolbox\Learning\InlineGrader\Kami\Model as KamiModel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Resource\Storage;
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

    public const MARK_VISIBILITY_STAFF_ONLY           = 0;
    public const MARK_VISIBILITY_STAFF_AND_STUDENT    = 1;
    public const MARK_VISIBILITY_STAFF_STUDENT_PARENT = 2;

    //Mark Visibility strings
    public const MARK_VISIBILITY_STAFF_ONLY_STRING = 'Staff Only';
    public const MARK_VISIBILITY_STAFF_AND_STUDENT_STRING = 'Staff & Student';
    public const MARK_VISIBILITY_STAFF_STUDENT_PARENT_STRING = 'Staff, Student & Parent';
    public const MARK_VISIBILITY_STRINGS = [
        0 => self::MARK_VISIBILITY_STAFF_ONLY_STRING,
        1 => self::MARK_VISIBILITY_STAFF_AND_STUDENT_STRING,
        2 => self::MARK_VISIBILITY_STAFF_STUDENT_PARENT_STRING,
    ];

    /**
     * @var int|null
     */
    protected $id;

    /**
     * @var GradeLevel\Group\Model
     */
    protected $gradeGroup;

    /**
     * @var SubmissionBox\SingleFile\Model[]|GenericCollection
     */
    protected $attachments;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var bool
     */
    protected $accept_online;

    /**
     * @var bool
     */
    protected $accept_multiple;

    /**
     * Is submission via a worksheet enabled?
     *
     * The student will submit via a copy of the worksheet referenced in
     * `$this->kamiWorksheetTemplate`.
     */
    protected bool $enableWorksheet = false;

    /**
     * The uploaded worksheet template, if there is one.
     */
    protected ?Storage\Model $worksheetTemplate = null;

    /**
     * The Kami document representation of `$this->worksheetTemplate`.
     */
    protected ?KamiModel $kamiWorksheetTemplate = null;

    /**
     * Is offline submission (student checks a box to confirm that they have
     * submitted a response to this due work) enabled?
     */
    protected bool $enableOffline = false;

    /**
     * Is submission via a rich text editor enabled?
     */
    protected bool $enableText = false;

    /**
     * Is submission via file upload enabled?
     */
    protected bool $enableFiles = false;

    /**
     * Does the student need to accept the school's submission policy before
     * submitting?
     */
    protected bool $enablePolicy = false;

    /**
     * @var Alaress_Date_Format
     */
    protected $created;

    /**
     * @var int
     */
    protected $weight = 0;

    /**
     * @var float
     */
    protected $max_mark;

    /**
     * @return int
     */
    protected $mark_type;

    /** @var bool */
    protected $useRubric;


    /**
     * The mark type chosen to represent capability totals
     *
     * @var MarkType|null
     */
    protected $capabilityMarkType;


    /**
     * The grade group associated with Letter Grade capability mark type
     *
     * @var GradeLevel\Group\Model|null
     */
    protected $capabilityGradeGroup;


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

    /** @var null|string */
    private $externalId;


    /**
     * SubmissionBox constructor.
     */
    public function __construct()
    {
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
    public function getMarkVisibilityDescription()
    {
        switch ($this->getMarkVisibility()) {
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
        $urgency = intval(($this->getAssessmentModel()->getDueDate()->getUnixTimestamp() - time()) / 86400);

        return max(0, min(10, $urgency));
    }

    public function __clone()
    {
        $this->id = null;
    }

    /* Getters/Setters
     */

    /**
     * @param  int|null $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
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
     * @param  float $max_mark
     * @return $this
     */
    public function setMaxMark($max_mark)
    {
        $this->max_mark = $max_mark;
        return $this;
    }

    /**
     * @return float
     */
    public function getMaxMark()
    {
        return $this->max_mark;
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
     * @return MarkType|null
     */
    public function getCapabilityMarkType(): ?MarkType
    {
        return $this->capabilityMarkType;
    }


    /**
     * @param MarkType|null $capabilityMarkType
     * @return $this
     */
    public function setCapabilityMarkType(?MarkType $capabilityMarkType): self
    {
        $this->capabilityMarkType = $capabilityMarkType;
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
     * @return GradeLevel\Group\Model|null
     */
    public function getGradeGroup()
    {
        return $this->gradeGroup;
    }

    /**
     * @param GradeLevel\Group\Model $gradeGroup
     * @return $this
     */
    public function setGradeGroup($gradeGroup)
    {
        $this->gradeGroup = $gradeGroup;
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
        $this->getAssessmentModel()
            ->setPublishDate($publish_date)
            ->setOpenDate($open_date)
            ->setDueDate($due_date)
            ->setCloseDate($late_date);
        return $this;
    }


    /**
     * Returns true if the current time is between the open and late dates of this box
     *
     * @return bool
     */
    public function isOpen()
    {
        return Alaress_Date_Format::now()->between(
            $this->getAssessmentModel()->getOpenDate(),
            $this->getAssessmentModel()->getCloseDate()
        );
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
     * @param int $requiredAccessLevel
     * @return bool
     */
    public function hasAccess(User $user, $requiredAccessLevel)
    {
        $access = Access::getInstance();

        /* Access level for $user on $submissionBox
         */
        $boxLevel = $access->getFolderAccess($user, $this->getAssessmentModel()->getContextFolder()->getId());

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


    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }


    /**
     * @param string|null $externalId
     * @return $this
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return GradeLevel\Group\Model|null
     */
    public function getCapabilityGradeGroup(): ?GradeLevel\Group\Model
    {
        return $this->capabilityGradeGroup;
    }

    /**
     * @param GradeLevel\Group\Model|null $capabilityGradeGroup
     * @return $this
     */
    public function setCapabilityGradeGroup(?GradeLevel\Group\Model $capabilityGradeGroup): self
    {
        $this->capabilityGradeGroup = $capabilityGradeGroup;
        return $this;
    }

    /**
     * @param bool $enableWorksheet
     * @return $this
     */
    public function setEnableWorksheet(bool $enableWorksheet): self
    {
        $this->enableWorksheet = $enableWorksheet;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableWorksheet(): bool
    {
        return $this->enableWorksheet;
    }

    /**
     * @param Storage\Model|null $worksheetTemplate
     * @return $this
     */
    public function setWorksheetTemplate(?Storage\Model $worksheetTemplate): self
    {
        $this->worksheetTemplate = $worksheetTemplate;
        return $this;
    }


    /**
     * @return KamiModel|null
     */
    public function getKamiWorksheetTemplate(): ?KamiModel
    {
        return $this->kamiWorksheetTemplate;
    }


    /**
     * @param KamiModel|null $kamiWorksheetTemplate
     * @return $this
     */
    public function setKamiWorksheetTemplate(?KamiModel $kamiWorksheetTemplate): self
    {
        $this->kamiWorksheetTemplate = $kamiWorksheetTemplate;
        return $this;
    }


    /**
     * @return Storage\Model|null
     */
    public function getWorksheetTemplate(): ?Storage\Model
    {
        return $this->worksheetTemplate;
    }

    /**
     * @param bool $enableOffline
     * @return $this
     */
    public function setEnableOffline(bool $enableOffline): self
    {
        $this->enableOffline = $enableOffline;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableOffline(): bool
    {
        return $this->enableOffline;
    }

    /**
     * @param bool $enableText
     * @return $this
     */
    public function setEnableText(bool $enableText): self
    {
        $this->enableText = $enableText;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableText(): bool
    {
        return $this->enableText;
    }

    /**
     * @param bool $enableFiles
     * @return $this
     */
    public function setEnableFiles(bool $enableFiles): self
    {
        $this->enableFiles = $enableFiles;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableFiles(): bool
    {
        return $this->enableFiles;
    }

    /**
     * @param bool $enablePolicy
     * @return $this
     */
    public function setEnablePolicy(bool $enablePolicy): self
    {
        $this->enablePolicy = $enablePolicy;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnablePolicy(): bool
    {
        return $this->enablePolicy;
    }
}

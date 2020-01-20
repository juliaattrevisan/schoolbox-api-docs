<?php
/**
 * Model for online assessments.
 *
 * @todo Add related models, such as question, answer, etc.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use \Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Traits\HasCurriculumNodes;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;

class Model implements ISoftDeletable, IAssessableDataModel
{
    use Id;

    // TODO. Update callers to this class to use createdAt() instead of created(), then uncomment next line
    // use CreatedAtDate;

    use HasCurriculumNodes;

    const AVAILABILITY_ASSESS = 'assess';
    const AVAILABILITY_SURVEY = 'survey';

    /**
     * Possible value for `$this->attempts`.
     *
     * This value specifies that a participant may make an unlimited number of attempts at this quiz.
     *
     * @var int
     */
    const ATTEMPTS_UNLIMITED = 0;

    /**
     * Possible value for `$this->duration`.
     *
     * This value specifies that a participant may take an unlimited amount of time to complete this quiz.
     *
     * @var int
     */
    const DURATION_UNLIMITED = -1;

    /**
    * @var Folder
    */
    protected $category;

    /**
    * @var User
    */
    protected $user;

    /**
    * Not strictly required, because it's always WorkType::TYPE_ONLINE_ASSESSMENT.
    *
    * @var WorkType
    */
    protected $work_type;

    /**
    * @var string
    */
    protected $title;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $completion_message;

    /**
    * @var Alaress_Date_Format
    */
    protected $start;

    /**
    * @var Alaress_Date_Format
    */
    protected $finish;

    /**
    * @var int
    */
    protected $duration;

    /**
    * @var int
    */
    protected $max_attempts;

    /**
    * @var int
    */
    protected $weight = 0;

    /**
    * @var string
    */
    protected $availability = self::AVAILABILITY_ASSESS;

    /**
    * @var bool
    */
    protected $anonymous_result;

    /**
    * @var bool
    */
    protected $notify_author;

    /**
    * @var Alaress_Date_Format
    */
    protected $created;

    /**
    * @var Alaress_Date_Format
    */
    protected $deleted;

    /**
     * @var int
     */
    protected $mark_visibility = 0;

    /** @var null|string */
    private $externalId;

    /**
     * Indexed by section.id
     * @var QuizSection[]|GenericCollection<QuizSection>
     */
    private $sections;

    /**
     * Indexed by attempt.id
     * @var QuizAttempt[]|GenericCollection<QuizAttempt>
     */
    private $attempts;


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
     * Is this quiz unlimited duration?
     *
     * @return bool
     */
    public function isDurationUnlimited(): bool
    {
        return $this->duration === self::DURATION_UNLIMITED;
    }

    /**
     * Does this quiz allow unlimited attempts?
     *
     * @return bool
     */
    public function isAttemptsUnlimited(): bool
    {
        return $this->attempts === self::ATTEMPTS_UNLIMITED;
    }

    /**
    * Get index of gradient
    *
    * @return int
    */
    public function getGradientIndex()
    {
        $urgency = intval(($this->getFinish()->getUnixTimeStamp() - time()) / 86400);

        return max(0, min(10, $urgency));
    }

    /**
     * Clears any attempts from the clone, and clones each Section in the Quiz
     * @return void
     */
    function __clone()
    {
        // Mark this object as a clone
        $this->id = null;

        // Clear Attempts
        $this->attempts = null;

        // Clone Sections to clear their IDs, so subsequent save is INSERT instead of UPDATE
        if ($this->sections !== null) {
            $clonedSections = new GenericCollection(QuizSection::class);
            foreach ($this->sections as $section) {
                $cloneSection = clone $section;
                $cloneSection->setQuiz($this);
                $clonedSections->add($cloneSection);
            }
            $this->sections = $clonedSections;
        }
    }

    /* Getters/Setters
     */

    /**
     * The base assessable model that is a part of this model.
     *
     * @var AssessmentModel
     */
    protected $assessmentModel;

    /**
     * @return AssessmentModel
     */
    public function getAssessmentModel()
    {
        return $this->assessmentModel;
    }

    /**
     * The assessment model must be allowed to be NULL, because this can be used
     * to hydrate surveys.
     *
     * @param AssessmentModel $assessmentModel
     * @return static
     */
    public function setAssessmentModel(AssessmentModel $assessmentModel = null)
    {
        $this->assessmentModel = $assessmentModel;
        return $this;
    }

    /**
    * @param  boolean $anonymous_result
    *
    * @return $this
    */
    public function setAnonymousResult($anonymous_result)
    {
        $this->anonymous_result = $anonymous_result;
        return $this;
    }

    /**
    * @return boolean
    */
    public function getAnonymousResult()
    {
        return $this->anonymous_result;
    }

    /**
    * @param  string $availability
    *
    * @return $this
    */
    public function setAvailability($availability)
    {
        $this->availability = $availability;
        return $this;
    }

    /**
    * @return string
    */
    public function getAvailability()
    {
        return $this->availability;
    }

    /**
    * @param  \Schoolbox\Resource\Folder\Model $category
    *
    * @return $this
    */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
    * @return \Schoolbox\Resource\Folder\Model
    */
    public function getCategory()
    {
        return $this->category;
    }

    /**
    * @param  \Alaress_Date_Format $created
    *
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
    * @param  string $description
    *
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
     * @return string
     */
    public function getCompletionMessage()
    {
        return $this->completion_message;
    }

    /**
     * @param  string $completion_message
     *
     * @return $this
     */
    public function setCompletionMessage($completion_message)
    {
        $this->completion_message = $completion_message;
        return $this;
    }

    /**
    * @param  int $duration
    *
    * @return $this
    */
    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }

    /**
    * @return int
    */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
    * @param  \Alaress_Date_Format $finish
    *
    * @return $this
    */
    public function setFinish($finish)
    {
        $this->finish = $finish;
        return $this;
    }

    /**
    * @return \Alaress_Date_Format
    */
    public function getFinish()
    {
        return $this->finish;
    }


    /**
    * @param  int $max_attempts
    *
    * @return $this
    */
    public function setMaxAttempts($max_attempts)
    {
        $this->max_attempts = $max_attempts;
        return $this;
    }

    /**
    * @return int
    */
    public function getMaxAttempts()
    {
        return $this->max_attempts;
    }

    /**
    * @param  boolean $notify_author
    *
    * @return $this
    */
    public function setNotifyAuthor($notify_author)
    {
        $this->notify_author = $notify_author;
        return $this;
    }

    /**
    * @return boolean
    */
    public function getNotifyAuthor()
    {
        return $this->notify_author;
    }

    /**
    * @param  \Alaress_Date_Format $start
    *
    * @return $this
    */
    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }

    /**
    * @return \Alaress_Date_Format
    */
    public function getStart()
    {
        return $this->start;
    }

    /**
    * @param  string $title
    *
    * @return $this
    */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
    * @return string
    */
    public function getTitle()
    {
        return $this->title;
    }

    /**
    * @param  \Schoolbox\User $user
    *
    * @return $this
    */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
    * @return \Schoolbox\User
    */
    public function getUser()
    {
        return $this->user;
    }

    /**
    * @param  WorkType $work_type
    *
    * @return $this
    */
    public function setWorkType($work_type)
    {
        $this->work_type = $work_type;
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
    * @param  \Alaress_Date_Format $deleted
    * @return $this
    */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
    * @return \Alaress_Date_Format
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
        return ($this->getDeleted() instanceof \Alaress_Date_Format && !$this->getDeleted()->isNull());
    }

    /**
    * @return int
    */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
    * @param  int   $weight
    * @return $this
    */
    public function setWeight($weight)
    {
        $this->weight = $weight;
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
     * Helper method for setting dates from a form.
     *
     * @param array $formDates
     * @return $this
     */
    public function setDatesFromForm($formDates)
    {
        $start_date  = isset($formDates['start_date'])
            ? \Alaress_Date_Format::newLocalShort($formDates['start_date'])->startOfDay()
            : \Alaress_Date_Format::now()->startOfDay();
        $finish_date = isset($formDates['finish_date'])
            ? \Alaress_Date_Format::newLocalShort($formDates['finish_date'])->endOfDay()
            : \Alaress_Date_Format::now()->endOfDay();
        $this->setStart($start_date);
        $this->setFinish($finish_date);
        return $this;
    }

    /**
     * @return QuizSection[]|GenericCollection<QuizSection>
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * @param GenericCollection<QuizSection> $sections
     * @return static
     */
    public function setSections(GenericCollection $sections)
    {
        $sections->assertCollectionOf(QuizSection::class);
        $this->sections = $sections;
        return $this;
    }

    /**
     * @return QuizAttempt[]|GenericCollection<QuizAttempt>
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * @param GenericCollection<QuizAttempt> $attempts
     * @return static
     */
    public function setAttempts(GenericCollection $attempts)
    {
        $attempts->assertCollectionOf(QuizAttempt::class);
        $this->attempts = $attempts;
        return $this;
    }
}

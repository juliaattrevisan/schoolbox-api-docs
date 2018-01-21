<?php
/**
 * DueWork
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use DomainException;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Rubric;
use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Assessment\Participant;

class Model extends AbstractAssessment
{
    /**
     * @var string
     */
    protected $defaultTypeName = 'Due Work';

    /**
     * Accept zero submissions online
     */
    public const ACCEPT_ONLINE_NONE = 0;

    /**
     * Accept one submission online
     */
    public const ACCEPT_ONLINE_SINGLE = 1;

    /**
     * Accept multiple submissions online
     */
    public const ACCEPT_ONLINE_MULTI = 2;

    /**
     * Do not accept self-reflection at all
     */
    public const SELF_REFLECTION_NONE = 0;

    /**
     * Accept only student self-reflection (no marking or rubric grades available)
     */
    public const SELF_REFLECTION_REFLECT_ONLY = 1;

    /**
     * Accept full student self-assessment (marking and rubric grades available)
     */
    public const SELF_REFLECTION_FULL = 2;

    /**
     * All the Files that are part of the Due Work specification
     * e.g. "Here are some pictures to look at, so you can complete
     * this Due Work"
     *
     * @var Collection|File[]
     */
    protected $attachedFiles;

    /**
     * An enum for accepting submissions online
     *
     * @var int
     */
    protected $acceptOnline;

    /**
     * Show students who haven't submitted to this Due Work
     *
     * @var bool
     */
    protected $showSubmittedOnly = false;

    /**
     * This DueWork uses a Rubric
     *
     * This property IS required. We cannot just inspect the attached Rubric because in some
     * scenarios (eg Collections) we don't hydrate the Rubric.
     *
     * @var bool
     */
    protected $useRubric = false;

    /**
     * @var Rubric|null
     */
    private $rubric;

    /**
     * This DueWork's Rubric has Mark Per Capability on
     *
     * @var bool
     */
    protected $markPerCapability = false;

    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var $int
     */
    private $maxMark;

    /**
     * Students may self-assess their submission to the Due Work, if selfAssessable is true
     *
     * @var bool
     */
    protected $selfAssessable;

    /**
     * Students may self-reflect their submission to the Due Work, if selfReflectable is true
     *
     * @var bool
     */
    protected $selfReflectable;

    /**
     * Model constructor.
     */
    public function __construct()
    {

        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAttachedFiles(new Collection());
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
        $this->setAttachedFiles(new Collection());
    }

    /**
     * Clones this object in preparation for persisting as a copy of this object.
     *
     * @return static
     */
    public function cloneForCopy()
    {
        /** @var static $clone */
        $clone = parent::cloneForCopy();

        /* Ensure any current rubric is NOT linked to new location.
         *
         * Because rubric cloning is so complex, it is handled specifically in the Replicator & Transplantor.
         * It is extremely likely this call does nothing. This is because even if this DueWork has a rubric,
         * it is unlikely to be hydrated at this point. This call merely protects the system in case someone
         * alters the default hydration.
         */
        $clone->setRubric(null);
        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function isFeedbackRequired(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        // No responses, or latest response has published feedback already ?
        $latestSubmittedResponse = $participant->findMostRecentSubmittedResponse();
        if (!$latestSubmittedResponse || $latestSubmittedResponse->hasPublishedFeedback()) {
            return false;
        }

        // One or more responses and no feedback ?
        $latestPublishedFeedback = $participant->findMostRecentPublishedFeedback();
        if (!$latestPublishedFeedback) {
            return true;
        }

        // Was the latest student response submitted after the latest mark was published?
        return $latestSubmittedResponse->getSubmittedAt() > $latestPublishedFeedback->getPublishedAt();
    }


    /**
     * The friendly, uninformative version of assertAcceptSubmissions.
     *
     * @param Collection $responses
     * @return bool
     */
    public function isAcceptingSubmissions(\Schoolbox\Collection $responses)
    {
        try {
            $this->assertAcceptSubmission($responses);
            return true;
        } catch (DomainException $e) {
            return false;
        }
    }

    /**
     * Work out if we are going to accept responses
     *
     * It's not accepting submissions if:
     *      - If it's not open
     *      - If the close date has passed
     *      - If the assessment is set to Accept None
     *      - If the assessment only accepts single, and a submitted response already exists for this participant
     *      - You're not a student of this assessment's class (folder)
     *
     * @param Collection $responses
     * @throws DomainException
     */
    public function assertAcceptSubmission(\Schoolbox\Collection $responses)
    {
        if ($this->getOpenAt()->gt(\Alaress_Date_Format::now())) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NOT_YET_OPEN);
        }

        if (\Alaress_Date_Format::now()->gt($this->getCloseAt())) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_CLOSED);
        }

        if ($this->isAcceptNone()) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_ONLINE_RESPONSE);
        }

        /* Allow draft responses in single submission assessments
         */
        if ($this->isAcceptSingle() && $responses->count() && $responses->first()->isSubmitted()) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_MULTIPLE_RESPONSE);
        }
    }


    /**
     * Check the dates set on this model are valid
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates()
    {
        /* Check publish date against everything else
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        /* Only check publish date */
        if ($this->getAcceptOnline() == self::ACCEPT_ONLINE_NONE) {
            $this->assertDateSequence([
                'publish' => $this->getPublishAt(),
                'due'     => $this->getDueAt()
            ]);

        } else {
            $this->assertDateSequence([
                'publish' => $this->getPublishAt(),
                'open'    => $this->getOpenAt(),
                'due'     => $this->getDueAt(),
                'late'    => $this->getCloseAt()
            ]);
        }
    }


    /**
     * @inheritdoc
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly)
    {
        // DueWork mark is cached on the feedback
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);

        // If no feedback, or feedback is floating, return null.
        return $feedback && $feedback->getResponse() ? $feedback->getResponse() : null;
    }


    /**
     * @inheritdoc
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly)
    {
        $this->assertSameAssessment($participant);

        // DueWork takes the mark from latest feedback
        return $publishedOnly
            ? $participant->findMostRecentPublishedFeedback()
            : $participant->findMostRecentFeedback();
    }


    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'dueWork';
    }


    /**
     * @inheritdoc
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly)
    {
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);
        return $feedback ? $feedback->getNormalisedMark() : null;
    }


    /**
     * DueWork does not use the MarkScheme. It merely returns the cached value.
     *
     * @inheritdoc
     */
    public function getHumanReadableMark(Participant $participant, $publishedOnly)
    {
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);
        return $feedback ? $feedback->getHumanReadableMark() : null;
    }


    /**
     * @inheritdoc
     */
    public function canLearnerCreateResponse(User $learner)
    {
        // Not open ?
        if(!$this->isOpen()){
            return false;
        }

        // Not a participant ?
        $participant = $this->getParticipantIfExtant($learner);
        if (!$participant) {
            return false;
        }

        // Does this DueWork not accept online responses ?
        if ($this->isAcceptNone()) {
            return false;
        }

        if ($this->isAcceptMulti()) {
            return true;
        }

        // Has student not submitted their single permitted response ?
        if ($this->isAcceptSingle() && !$participant->hasSubmittedResponse()) {
            return true;
        }

        // Logically impossible - but be defensive
        return false;
    }


    /**
     * @inheritdoc
     */
    public function canInstructorCreateResponse(User $instructor, User $learner)
    {
        // Specified student not a participant ?
        $participant = $this->getParticipantIfExtant($learner);
        if (!$participant) {
            return false;
        }

        // Instructors are not constrained by the submission limit, or the open duration
        return true;
    }

    /* Getters/Setters
     */

    /**
     * Override: if this doesn't accept submissions, open is aliased to publish
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        if ($this->isAcceptNone()) {
            return $this->getPublishAt();
        } else {
            return parent::getOpenAt();
        }
    }

    /**
     * Override: if this doesn't accept submissions, close is aliased to due
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        if ($this->isAcceptNone()) {
            return $this->getDueAt();
        } else {
            return parent::getCloseAt();
        }
    }

    /**
     * This DueWork accepts no submissions online
     *
     * @return $this
     */
    public function setAcceptsNone()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
        return $this;
    }

    /**
     * Does this DueWork accept no submission online?
     * @return bool
     */
    public function isAcceptNone()
    {
        return $this->getAcceptOnline() == self::ACCEPT_ONLINE_NONE;
    }

    /**
     * This DueWork accepts one submissions online
     *
     * @return $this
     */
    public function setAcceptsSingle()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_SINGLE);
        return $this;
    }

    /**
     * Does this DueWork accept one submission online?
     *
     * @return bool
     */
    public function isAcceptSingle()
    {
        return $this->getAcceptOnline() == self::ACCEPT_ONLINE_SINGLE;
    }

    /**
     * This DueWork accepts multiple submissions online
     *
     * @return $this
     */
    public function setAcceptsMultiple()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_MULTI);
        return $this;
    }

    /**
     * Does this DueWork accept multiple submissions online?
     * @return bool
     */
    public function isAcceptMulti()
    {
        return $this->getAcceptOnline() == self::ACCEPT_ONLINE_MULTI;
    }

    /**
     * Only show Students who have submitted to this DueWork
     *
     * @return $this
     */
    public function showOnlySubmittedStudents()
    {
        $this->setShowSubmittedOnly(true);
        return $this;
    }

    /**
     * Show all Students who can submit to this DueWork
     *
     * @return $this
     */
    public function showAllSubmissibleStudents()
    {
        $this->setShowSubmittedOnly(false);
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUseRubric()
    {
        return $this->useRubric;
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
     * @return Rubric|null
     */
    public function getRubric()
    {
        return $this->rubric;
    }

    /**
     * @param Rubric|null $rubric
     * @return static
     */
    public function setRubric(Rubric $rubric = null)
    {
        $this->rubric = $rubric;
        return $this;
    }

    /**
     * @return int
     */
    public function getAcceptOnline()
    {
        return $this->acceptOnline;
    }

    /**
     * @param int $acceptOnline
     * @return $this
     */
    public function setAcceptOnline($acceptOnline)
    {
        $this->acceptOnline = $acceptOnline;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getShowSubmittedOnly()
    {
        return $this->showSubmittedOnly;
    }

    /**
     * @param boolean $showSubmittedOnly
     * @return $this
     */
    public function setShowSubmittedOnly($showSubmittedOnly)
    {
        $this->showSubmittedOnly = $showSubmittedOnly;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxMark()
    {
        return $this->maxMark;
    }

    /**
     * @param int $maxMark
     * @return static
     */
    public function setMaxMark($maxMark)
    {
        $this->maxMark = $maxMark;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getMarkPerCapability()
    {
        return $this->markPerCapability;
    }

    /**
     * @param boolean $markPerCapability
     * @return $this
     */
    public function setMarkPerCapability($markPerCapability)
    {
        $this->markPerCapability = $markPerCapability;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAttachedFiles()
    {
        return $this->attachedFiles;
    }

    /**
     * @param Collection $attachedFiles
     * @return $this
     */
    public function setAttachedFiles($attachedFiles)
    {
        $this->attachedFiles = $attachedFiles;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getResponseClass()
    {
        return Response\Model::class;
    }

    /**
     * @inheritdoc
     */
    public function getFeedbackClass()
    {
        return Feedback\Model::class;
    }

    /**
     * @return boolean
     */
    public function getSelfAssessable()
    {
        return $this->selfAssessable;
    }

    /**
     * @param boolean $selfAssessable
     * @return static
     */
    public function setSelfAssessable(bool $selfAssessable)
    {
        $this->selfAssessable = $selfAssessable;
        return $this;
    }

    /**
    * Determine whether this due work is self-assessable
    *
    * @return bool
    */
    public function isSelfAssessable()
    {
        return $this->selfAssessable;
    }

    /**
     * @return bool
     */
    public function getSelfReflectable() : ?bool
    {
        return $this->selfReflectable;
    }

    /**
     * @param bool $selfReflectable
     * @return static
     */
    public function setSelfReflectable(bool $selfReflectable)
    {
        $this->selfReflectable = $selfReflectable;
        return $this;
    }

    /**
     * Alias for `getSelfReflectable()`
     *
     * @return bool
     */
    public function isSelfReflectable() : ?bool
    {
        return $this->selfReflectable;
    }

    /**
     * Set self-assessment and reflection based on a `self::SELF_REFLECTION_*`
     * constant.
     *
     * @param int $selfReflectionType a `self::SELF_REFLECTION_*` constant
     * @return $this
     */
    public function setSelfReflectionType(int $selfReflectionType): self
    {
        switch ($selfReflectionType) {
            case self::SELF_REFLECTION_REFLECT_ONLY:
                $this->setSelfReflectable(true)->setSelfAssessable(false);
                break;
            case self::SELF_REFLECTION_FULL:
                $this->setSelfReflectable(true)->setSelfAssessable(true);
                break;
            case self::SELF_REFLECTION_NONE:
            default:
                $this->setSelfReflectable(false)->setSelfAssessable(false);
                break;
        }
        return $this;
    }

    /**
     * Get self-reflection type.
     *
     * @return int a `self::SELF_REFLECTION_*` constant
     */
    public function getSelfReflectionType(): int
    {
        $selfReflectable = $this->isSelfReflectable();
        $selfAssessable  = $this->isSelfAssessable();
        if (!$selfReflectable) {
            return self::SELF_REFLECTION_NONE;
        } else {
            return $selfAssessable ? self::SELF_REFLECTION_FULL : self::SELF_REFLECTION_REFLECT_ONLY;
        }
    }
}

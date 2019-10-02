<?php
/**
 * DueWork
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use DomainException;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\ISelfAssessable;
use UnexpectedValueException;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox;
use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Assessment\Participant;

class Model extends AbstractAssessment implements ISelfAssessable
{
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
     * @var Grid|null
     */
    private $rubricGrid;

    /**
     * This DueWork's Rubric has Mark Per Capability on
     *
     * @var bool
     */
    protected $markPerCapability = false;

    /**
     * This DueWork's Rubric has Use Mark Range on
     *
     * @var bool
     */
    protected $useMarkRange = false;

    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var float
     */
    private $maxMark;

    /**
     * @var string
     * @see SubmissionBox::SELF_REVIEWABILITY_NONE
     * @see SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY
     * @see SubmissionBox::SELF_REVIEWABILITY_FULL
     */
    private $selfReviewability;


    /**
     * @var bool
     */
    private $similarityCheckEnabled = false;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return 'Due Work';
    }


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

        // copy attachments
        $newFiles = new Collection();
        foreach ($this->getAttachedFiles() as $file) {
            $newFiles->add((new File())
                ->setStorage($file->getStorage())
                ->setBasename($file->getBasename()));
        }
        $clone->setAttachedFiles($newFiles);

        /* Ensure any current rubric is NOT linked to new location.
         *
         * Because rubric cloning is so complex, it is handled specifically in the Replicator & Transplantor.
         * It is extremely likely this call does nothing. This is because even if this DueWork has a rubric,
         * it is unlikely to be hydrated at this point. This call merely protects the system in case someone
         * alters the default hydration.
         */
        $clone->setRubricGrid(null);
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
        if (!$latestSubmittedResponse || $latestSubmittedResponse->findMostRecentInstructorGivenPublishedFeedback()) {
            return false;
        }

        // One or more responses and no feedback ?
        $latestPublishedFeedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
        if (!$latestPublishedFeedback) {
            return true;
        }

        // Was the latest student response submitted after the latest mark was published?
        return $latestSubmittedResponse->getSubmittedAt() > $latestPublishedFeedback->getPublishedAt();
    }

    /**
     * Work out if we are going to accept responses
     *
     * It's not accepting submissions if:
     *      - It's a spec
     *      - If it's not open
     *      - If the close date has passed
     *      - If the assessment is set to Accept None
     *      - If the assessment only accepts single, and a submitted response already exists for this participant
     *      - You're not a student of this assessment's class (folder)
     *
     * @param null|Participant $participant
     */
    public function assertAcceptSubmission(?Participant $participant)
    {
        if ($this->isSpecification()) {
            throw new DomainException(null, ErrorMapper::ERROR_NO_COURSE_SPEC_INTERACTIONS);
        }
        $this->assertOpen($participant);

        if ($this->isAcceptNone()) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_ONLINE_RESPONSE);
        }

        /* Allow draft responses in single submission assessments
         */
        if ($this->isAcceptSingle()) {
            $allResponses = $participant ? $participant->getAllResponses() : new Collection();
            $this->assertNoPublishedResponses($allResponses);
        }
    }


    /**
     * Can a participant create an acknowledgement response?
     *
     * This action is only available to open due works that the participant has
     * not yet responded to.
     *
     * @param null|Participant $participant
     * @throws DomainException If this due work does not accept submission acknowledgment
     */
    public function assertAcceptAcknowledgement(?Participant $participant)
    {
        if ($this->isSpecification()) {
            throw new DomainException(null, ErrorMapper::ERROR_NO_COURSE_SPEC_INTERACTIONS);
        }
        $this->assertOpen($participant);

        $allResponses = $participant ? $participant->getAllResponses() : new Collection();
        $this->assertNoPublishedResponses($allResponses);
    }

    /**
     * Assert whether this due work is open.
     *
     * @throws DomainException
     */
    private function assertOpen(Participant $participant = null)
    {
        if ($participant != null) {
            $openDate = $participant->getOpenDate();
            $closeDate = $participant->getCloseDate();
        } else {
            $openDate = $this->getOpenAt();
            $closeDate = $this->getCloseAt();
        }

        if ($openDate->gt(\Alaress_Date_Format::now())) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NOT_YET_OPEN);
        }

        if (\Alaress_Date_Format::now()->gt($closeDate)) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_CLOSED);
        }
    }

    /**
     * @param Collection|IResponse[] $responses
     * @throws DomainException
     */
    private function assertNoPublishedResponses(Collection $responses)
    {
        foreach ($responses as $response) {
            if ($response->isSubmitted()) {
                throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_MULTIPLE_RESPONSE);
            }
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
            ? $participant->findMostRecentInstructorGivenPublishedFeedback()
            : $participant->findMostRecentInstructorGivenFeedback();
    }


    /**
     * @inheritdoc
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        $this->assertSameAssessment($participant);

        $anyAuthoritativeFeedback = $this->getAuthoritativeFeedback($participant, false);
        if (!$anyAuthoritativeFeedback) {
            // No feedback at all? Then there cannot be a pending draft.
            return false;
        }
        if ($anyAuthoritativeFeedback->isDraft()) {
            // Latest feedback is a draft.
            return true;
        }
        // latest feedback is published. No draft pending.
        return false;
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
        if(!$this->isOpen($learner)){
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
     * Is the given user allowed to acknowledge that they have submitted work offline?
     *
     * @param User $learner
     * @throws UnexpectedValueException
     * @return bool
     */
    public function canLearnerCreateAcknowledgement(User $learner)
    {
        // if self-assess is off, use the same rules as response-create
        if (!$this->isSelfAssessable()) {
            return $this->canLearnerCreateResponse($learner);
        }

        // ok, rules for self-assess

        // not allowed if the DW isn't open for the user yet (or ever)
        if(!$this->isOpen($learner)){
            return false;
        }

        // not allowed if you're not a participant
        $participant = $this->getParticipantIfExtant($learner);
        if (!$participant) {
            return false;
        }

        /*
         * ** Rules **
         * Accept None: ack as often as you like
         * Accept One: ack once
         * Accept Multi: ack as often as you like
         */

        if ($this->isAcceptMulti() || $this->isAcceptNone()) {
            return true;
        }

        // not submitted a response yet? ok you can ACK
        if (!$participant->hasSubmittedResponse()) {
            return true;
        }

        return false;
    }


    /**
     * @inheritdoc
     */
    public function canInstructorCreateResponse(User $instructor, User $submittedOnBehalfOf)
    {
        // Specified student not a participant ?
        $participant = $this->getParticipantIfExtant($submittedOnBehalfOf);
        if ($participant) {
            return true;
        }

        // Is instructor wishing to create response for themself?
        if ($instructor->getId() == $submittedOnBehalfOf->getId()) {
            // If no participation, must be a dynamic plan available
            return $this->hasDynamicPlan();
        }

        return false;

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
     * @return null|Grid
     */
    public function getRubricGrid(): ?Grid
    {
        return $this->rubricGrid;
    }

    /**
     * @param null|Grid $rubricGrid
     * @return static
     */
    public function setRubricGrid(?Grid $rubricGrid): self
    {
        $this->rubricGrid = $rubricGrid;
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
     * @return float|null
     */
    public function getMaxMark(): ?float
    {
        return $this->maxMark;
    }

    /**
     * @param float|null $maxMark
     * @return static
     */
    public function setMaxMark(?float $maxMark): self
    {
        $this->maxMark = $maxMark;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getMarkPerCapability() : bool
    {
        return $this->markPerCapability;
    }

    /**
     * @param boolean $markPerCapability
     * @return $this
     */
    public function setMarkPerCapability(bool $markPerCapability)
    {
        $this->markPerCapability = $markPerCapability;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUseMarkRange() : bool
    {
        return $this->useMarkRange;
    }

    /**
     * @param boolean $useMarkRange
     * @return $this
     */
    public function setUseMarkRange(bool $useMarkRange)
    {
        $this->useMarkRange = $useMarkRange;
        return $this;
    }

    /**
     * @return Collection|File[]
     */
    public function getAttachedFiles()
    {
        return $this->attachedFiles;
    }

    /**
     * @param Collection|File[] $attachedFiles
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
     * @return null|string
     */
    public function getSelfReviewability(): ?string
    {
        return $this->selfReviewability;
    }


    /**
     * @param string $selfReviewability
     * @return $this
     */
    public function setSelfReviewability(string $selfReviewability): self
    {
        $this->selfReviewability = $selfReviewability;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSimilarityCheckEnabled(): bool
    {
        return $this->similarityCheckEnabled;
    }

    /**
     * @param bool $similarityCheckEnabled
     * @return Model
     */
    public function setSimilarityCheckEnabled(bool $similarityCheckEnabled): self
    {
        $this->similarityCheckEnabled = $similarityCheckEnabled;
        return $this;
    }


    /**
     * Can learners give feedback on their own submissions?
     *
     * Returns TRUE if the assessment is set to reflect-only or full
     *
     * @return bool
     * @throws UnexpectedValueException If property is not set
     */
    public function isSelfAssessable(): bool
    {
        if (null === $this->selfReviewability) {
            throw new UnexpectedValueException('Property not set');
        }
        $selfAssessableStates = [SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY, SubmissionBox::SELF_REVIEWABILITY_FULL];
        return \in_array($this->selfReviewability, $selfAssessableStates, true);
    }

    /**
     * Can learners *only* reflect on their own work?
     *
     * Returns FALSE if the assessment is self-gradable
     *
     * @return bool
     * @throws UnexpectedValueException If property is not set
     */
    public function isSelfReflectableOnly(): bool
    {
        if (null === $this->selfReviewability) {
            throw new UnexpectedValueException('Property not set');
        }
        return $this->selfReviewability === SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY;
    }


    /**
     * Returns whether students can create feedback with marks on their own submissions.
     *
     * @return bool
     * @throws UnexpectedValueException If property is not set
     */
    public function isSelfGradable(): bool
    {
        if (null === $this->selfReviewability) {
            throw new UnexpectedValueException('Property not set');
        }
        return $this->selfReviewability === SubmissionBox::SELF_REVIEWABILITY_FULL;
    }
}

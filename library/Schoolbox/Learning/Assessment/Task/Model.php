<?php

/**
 * Task
 *
 * A generic "thing to do" that doesn't have a Mark value. A Task
 * typically represents things like "read this book", "prepare this
 * thing before the class" etc.
 *
 * Tasks can be self-assessed (like a to-do list for students)
 * or they can be asssessed by a teacher ("student read the book", etc)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;

/**
 * When a student self-assesses a task, it will generate an IResponse only, with a mark of 'incomplete' or 'self-assessed'.
 * When a teacher assesses a student, it will generate both an IResponse and an IFeedback, with a mark of 'incomplete' or 'teacher-assessed'.
 *
 */
class Model extends AbstractAssessment
{
    /**
     * Is this task self-markable?
     *
     * @var bool
     */
    protected $isSelfAssessable = false;

    /**
     * The content this task links to (if any).
     *
     * @var string
     */
    protected $url = '';


    /**
     * Optionally attached Files
     *
     * @var Collection|File[]
     */
    protected $attachedFiles;

    /**
     * Model constructor.
     */
    public function __construct()
    {

        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAttachedFiles(new Collection());
    }

    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return 'Task';
    }


    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'task';
    }

    /**
     * Tasks always return null.
     *
     * @inheritdoc
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly)
    {
        // No concept of 'publishing' task marks

        // Can a student mark themselves as completing this task?
        if ($this->isSelfAssessable) {
            return $participant->findMostRecentResponse();
        }
        // Else only a teacher can mark the task, which always generates a feedback and response
        $feedback = $this->getAuthoritativeFeedback($participant, $publishedOnly);
        return $feedback ? $feedback->getResponse() : null;
    }


    /**
     * @inheritdoc
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly)
    {
        $this->assertSameAssessment($participant);

        // The authoritative task feedback is always the latest one
        return $publishedOnly
            ? $participant->findMostRecentInstructorGivenPublishedFeedback()
            : $participant->findMostRecentInstructorGivenFeedback();
    }

    /**
     * @inheritDoc
     */
    public function cloneForCopy()
    {
        /** @var static $clone */
        $clone = parent::cloneForCopy();

        // copy attachments
        $clone->copyAttachedFiles($this);

        return $clone;
    }

    /**
     * Overwrite fields from `$source` into this object.
     *
     * This implementation overwrites the additional fields over and above the
     * base implementation:
     * * attached files
     * * self-assessable
     * * URL
     *
     * @param self $source
     * @return $this
     */
    public function overwrite(IAssessable $source): IAssessable
    {
        parent::overwrite($source);
        $this->isSelfAssessable = $source->isSelfAssessable;
        $this->url              = $source->url;
        $this->copyAttachedFiles($source);
        return $this;
    }

    /**
     * Copy attached files from `$source` into this object.
     *
     * @param self $source
     * @return $this
     */
    private function copyAttachedFiles(self $source): self
    {
        $newFiles = new Collection();
        foreach ($source->getAttachedFiles() as $file) {
            $newFiles->add(
                (new File())
                    ->setStorage($file->getStorage())
                    ->setBasename($file->getBasename())
            );
        }
        $this->setAttachedFiles($newFiles);
        return $this;
    }

    /**
     * @param GradeGroup|null $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup = null)
    {
        if (!is_null($gradeGroup)) {
            throw new \InvalidArgumentException('Tasks cannot have mark type of Letter Grade.');
        }
        return $this;
    }

    /**
     * Task marks are always visible to all users which have permission to view it.
     *
     * @return int
     */
    public function getMarkVisibility()
    {
        return self::MARK_VISIBILITY_PARENT_STUDENT;
    }

    /**
     * Task marks are always visible to all users which have permission to view it.
     *
     * @param int $markVisibility
     * @return static
     */
    public function setMarkVisibility($markVisibility)
    {
        return $this;
    }

    /**
     * Tasks have no concept of mark visibility - marks are always visible.
     *
     * @param User $viewingUser
     * @return bool
     */
    public function isFeedbackVisibleToUser(User $viewingUser)
    {
        return $this->isFeedbackVisibleToRoleType($viewingUser->getRole()->getType());
    }


    /**
     * @inheritDoc
     */
    public function isFeedbackVisibleToRoleType(string $roleType): bool
    {
        return true;
    }

    /**
     * Tasks do not have any weight.
     *
     * @return float
     */
    public function getWeight()
    {
        return 0;
    }


    /**
     * Check the dates set on this model are valid
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates()
    {
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'due'     => $this->getDueAt()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function canLearnerCreateResponse(User $learner)
    {
        // Not a participant ?
        if (!$this->isParticipant($learner)) {
            return false;
        }

        // Not open ?
        if (!$this->isOpen($learner)) {
            return false;
        }

        // Must be self-assessable
        if (!$this->isSelfAssessable()) {
            return false;
        }

        // If teacher has marked as complete, then student cannot alter back to 'incomplete'
        /** @var TaskResponse $latestResponse */
        $latestResponse = $this->getParticipant($learner)->findMostRecentSubmittedResponse();
        if ($latestResponse && !$latestResponse->isSelfAssessed() && $latestResponse->getMarkedComplete()) {
            return false;
        }

        // Task is open, self-assessable, and not yet marked by the teacher as 'complete'
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isResponseRequired(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        /** @var TaskResponse $mostRecentResponse */
        $mostRecentResponse = $participant->findMostRecentSubmittedResponse();
        return !$mostRecentResponse || !$mostRecentResponse->getMarkedComplete();
    }

    /**
     * @inheritdoc
     */
    public function isFeedbackRequired(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        /** @var TaskResponse $latestSubmittedResponse */
        $latestSubmittedResponse = $participant->findMostRecentSubmittedResponse();
        $result =  $latestSubmittedResponse
            && $latestSubmittedResponse->getMarkedComplete()
            && !$latestSubmittedResponse->hasFeedback();
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function isReviewComplete(Participant $participant)
    {
        $this->assertSameAssessment($participant);

        /** @var TaskResponse $latestSubmittedResponse */
        $latestSubmittedResponse = $participant->findMostRecentSubmittedResponse();
        return $latestSubmittedResponse
            && $latestSubmittedResponse->getMarkedComplete()
            && $latestSubmittedResponse->hasFeedback();
    }

    /**
     * Special case for task - can be 'marked' by student or teacher.
     *
     * @inheritdoc
     */
    public function hasMark(Participant $participant)
    {
        // Has the task been marked ?
        // Note this distinguishes between implicit 'Incomplete' and explicit 'Incomplete'.
        // The former returns FALSE, while the latter returns TRUE
        return $participant->getAllResponses()->count() > 0;
    }


    /**
     * @inheritdoc
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        // Tasks do not have draft marks
        $this->assertSameAssessment($participant);
        return false;
    }


    /**
     * Convenience intuitive method (but not a mapper standard get/set)
     *
     * @return bool
     * @throws InsufficientHydrationException If property is not set
     */
    public function isSelfAssessable(): bool
    {
        if (null === $this->isSelfAssessable) {
            throw new InsufficientHydrationException('Property not set');
        }
        return $this->isSelfAssessable;
    }


    /* Getters/Setters
     */

    /**
     * @return boolean
     */
    public function getIsSelfAssessable()
    {
        return $this->isSelfAssessable;
    }

    /**
     * @param boolean $isSelfAssessable
     * @return $this;
     */
    public function setIsSelfAssessable($isSelfAssessable)
    {
        $this->isSelfAssessable = $isSelfAssessable;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this;
     */
    public function setUrl($url)
    {
        $this->url = $url;
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
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        return $this->getPublishAt();
    }

    /**
     * @param \Alaress_Date_Format $openAt
     * @return static
     */
    public function setOpenAt(\Alaress_Date_Format $openAt)
    {
        //noop
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        return $this->getDueAt();
    }

    /**
     * @param \Alaress_Date_Format $closeAt
     * @return static
     */
    public function setCloseAt(\Alaress_Date_Format $closeAt)
    {
        // noop
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getResponseClass()
    {
        return TaskResponse::class;
    }

    /**
     * @inheritdoc
     */
    public function getFeedbackClass()
    {
        return TaskFeedback::class;
    }
}

<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent;

use InvalidArgumentException;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\User;

class Model extends AbstractAssessment
{
    /**
     * This appears on Create Activity screens but you probably need to rename
     * this to something friendlier because it's user-facing.
     *
     * TODO Rename to whatever this is canonically called
     */
    private const DEFAULT_TYPE_NAME = 'Dialog Agent';


    /**
     * The IAssessable associated with this Dialog Agent
     */
    private ?AssessmentModel $assessmentModel = null;


    /**
     * The user who created this Dialog Agent
     *
     * @var User|null
     */
    private ?User $createdBy = null;


    /**
     * The user who most recently updated this Dialog Agent
     *
     * @var User|null
     */
    private ?User $updatedBy = null;


    /**
     * The user who deleted this Dialog Agent
     *
     * @var User|null
     */
    private ?User $deletedBy = null;


    /**
     * @var string|null
     */
    private ?string $duration = null;


    /**
     * @return string
     */
    public static function getDefaultTypeName(): string
    {
        return self::DEFAULT_TYPE_NAME;
    }


    /**
     * Decide if $learner can create a response to this Dialog Agent.
     *
     * @param User $learner
     * @return bool
     */
    public function canLearnerCreateResponse(User $learner): bool
    {
        // Not a participant?
        if (!$this->isParticipant($learner)) {
            return false;
        }

        // Not open?
        if (!$this->isOpen($learner)) {
            return false;
        }

        /* TODO Maybe something about activity duration should be in here...?
         */

        return true;
    }


    /**
     * This model's "kind of assessment" used to identify it
     * among the family of AbstractAssessment subclasses
     *
     * @return string
     */
    public static function getFamilyName(): string
    {
        return AssessmentType::TYPE_DIALOG_AGENT;
    }


    /**
     * Dialog Agents never have marks.
     *
     * @param Participant $participant
     * @param $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(Participant $participant, $publishedOnly): ?float
    {
        return null;
    }


    /**
     * Decide which Dialog Agent conversation made by students should be considered canonical.
     * Note that we have a bool to decide which one should be considered authoritative, rather
     * than simply depend on time such as "most recent conversation"
     *
     * @param Participant $participant
     * @param $publishedOnly
     * @return IResponse|null
     */
    public function getAuthoritativeResponse(Participant $participant, $publishedOnly): ?IResponse
    {
        // TODO: Implement getAuthoritativeResponse() method.
        //  foreach ($participant responses) {
        //      if $response->getAuthoritative() {
        //          return true;
        //      }
        //  }

        return null;
    }


    /**
     * Same principle as getAuthoritativeResponse as each Response will have an ai-generated
     * Feedback directly associated with it 1:1
     *
     * @param Participant $participant
     * @param $publishedOnly
     * @return IFeedback|null
     */
    public function getAuthoritativeFeedback(Participant $participant, $publishedOnly): ?IFeedback
    {
        // TODO: Implement getAuthoritativeFeedback() method.
        //  foreach ($participant responses) {
        //      if $response->getAuthoritative() {
        //          return true;
        //      }
        //  }

        return null;
    }


    /**
     * TODO Not entirely sure what this means for Dialog Agents atm.
     *  I think we can get away with "no feedback required" (false) for now
     *
     * @param Participant $participant
     * @return bool
     */
    public function isFeedbackRequired(Participant $participant): bool
    {
        // TODO: Implement isFeedbackRequired() method.
        return false;
    }


    /**
     * TODO Not entirely sure what this means for Dialog Agents atm.
     *  I think we can get away with "no feedback required" (false) for now because the feedback
     *  that's generated will have no requirement for a human to help compose it.
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasPendingDraftAuthoritativeFeedback(Participant $participant): bool
    {
        // TODO: Implement hasPendingDraftAuthoritativeFeedback() method.
        return false;
    }


    /**
     * Check the dates set on this model are valid
     *
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates(): void
    {
        /* Check publish date against everything else
         * Don't check dates if in course
         */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        $this->assertDateSequence([
            'publish' => $this->getPublishAt(),
            'open' => $this->getOpenAt(),
            'due' => $this->getDueAt(),
            'late' => $this->getCloseAt()
        ]);
    }


    /**
     * @return AssessmentModel|null
     */
    public function getAssessmentModel(): ?AssessmentModel
    {
        return $this->assessmentModel;
    }


    /* Getters/Setters
     */

    /**
     * @param AssessmentModel|null $assessmentModel
     * @return void
     */
    public function setAssessmentModel(?AssessmentModel $assessmentModel): void
    {
        $this->assessmentModel = $assessmentModel;
    }


    /**
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }


    /**
     * @param User|null $createdBy
     * @return $this
     */
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }


    /**
     * @param User|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }


    /**
     * @param User|null $deletedBy
     * @return $this
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDuration(): ?string
    {
        return $this->duration;
    }


    /**
     * @param string|null $duration
     * @return $this
     */
    public function setDuration(?string $duration): self
    {
        $this->duration = $duration;
        return $this;
    }
}

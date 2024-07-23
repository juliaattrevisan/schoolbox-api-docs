<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\Mapper\Traits\Model\Id;

class Model
{
    use Id;

    /**
     * Assessment
     */
    private int $assessmentId;

    /**
     * @var User The user whose work was viewed.
     */
    private User $participant;
    private int $participantId;

    /**
     * @var User The user who viewed the assessment.
     */
    private User $user;
    private User $userId;

    /**
     * @var Alaress_Date_Format The time the view occurred.  Only the most recent view is stored.
     */
    private Alaress_Date_Format $lastVisit;

    /**
     * @var bool Feedback existed and was visible to the user at the time the assessment was viewed.
     *           A mark counts as feedback, so this will be true if a mark existed, and the assessment's mark
     *           visibility included the viewer's role type at the time the assessment was viewed.
     *           @see /Learning/Assessment/Views/LatestView->addNewView().
     */
    private bool $feedbackWasVisible;

    /**
     * @return int
     */
    public function getAssessmentId(): int
    {
        return $this->assessmentId;
    }

    /**
     * @param IAssessable $assessment
     * @return $this
     *
     */
    public function setAssessment(IAssessable $assessment): self
    {
        $this->assessmentId = $assessment->getId();
        return $this;
    }

    /**
     * @param int $assessmentId
     * @return $this
     */
    public function setAssessmentId(int $assessmentId): self
    {
        $this->assessmentId = $assessmentId;
        return $this;
    }

    /**
     * @return int
     */
    public function getParticipantId(): int
    {
        return $this->participantId;
    }

    /**
     * @return User
     */
    public function getParticipant(): User
    {
        return $this->participant;
    }

    /**
     * @param User $participant
     * @return $this
     */
    public function setParticipant(User $participant): self
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * @param int $participantId
     * @return $this
     *
     */
    public function setParticipantId(int $participantId): self
    {
        $this->participantId = $participantId;
        return $this;
    }

    /**
     * @return User
     */
    public function getUserId(): User
    {
        return $this->userId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     *
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param User $userId
     * @return $this
     *
     */
    public function setUserId($userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getLastVisit(): Alaress_Date_Format
    {
        return $this->lastVisit;
    }

    /**
     * @param Alaress_Date_Format $lastVisit
     * @return $this
     *
     */
    public function setLastVisit(Alaress_Date_Format $lastVisit): self
    {
        $this->lastVisit = $lastVisit;
        return $this;
    }

    /**
     * @return bool
     */
    public function getFeedbackWasVisible(): bool
    {
        return $this->feedbackWasVisible;
    }

    /**
     * @param bool $feedbackWasVisible
     * @return $this
     *
     */
    public function setFeedbackWasVisible(bool $feedbackWasVisible): self
    {
        $this->feedbackWasVisible = $feedbackWasVisible;
        return $this;
    }
}

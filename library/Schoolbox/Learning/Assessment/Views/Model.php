<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

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
     * Participant
     */
    private int $participantId;

    /**
     * User
     */
    private int $userId;

    /**
     * last Visit
     */
    private \Alaress_Date_Format $last_visit;

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
     * @param User $participant
     * @return $this
     *
     */
    public function setParticipant(User $participant): self
    {
        $this->participantId = $participant->getId();
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
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }


    /**
     * @param User $user
     * @return $this
     *
     */
    public function setUser(User $user): self
    {
        $this->userId = $user->getId();
        return $this;
    }

    /**
     * @param int $userId
     * @return $this
     *
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getLastVisit(): \Alaress_Date_Format
    {
        return $this->last_visit;
    }


    /**
     * @param \Alaress_Date_Format $last_visit
     * @return $this
     *
     */
    public function setLastVisit(\Alaress_Date_Format $last_visit): self
    {
        $this->last_visit = $last_visit;
        return $this;
    }
}

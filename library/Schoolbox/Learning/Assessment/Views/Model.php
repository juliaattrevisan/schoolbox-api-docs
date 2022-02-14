<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Schoolbox\User;
use Schoolbox\Learning\Assessment\DataModel as LearningAssessment;

class Model
{

    /**
     * Assessment Id
     */
    private LearningAssessment\Model $assessment;

    /**
     * Participant
     */
    private User $participant;

    /**
     * User
     */
    private User $user;

    /**
     * last Visit
     */
    private \Alaress_Date_Format $last_visit;

    /**
     * @return LearningAssessment\Model
     */
    public function getAssessment(): LearningAssessment\Model
    {
        return $this->assessment;
    }


    /**
     * @param LearningAssessment\Model $assessment
     * @return $this
     *
     */
    public function setAssessment(LearningAssessment\Model $assessment): self
    {
        $this->assessment = $assessment;
        return $this;
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
     *
     */
    public function setParticipant(User $participant): self
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * @return User
     */
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

<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Participant;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\User;
use Alaress_Date_Format;

class Model
{

    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * @var Plan\Model
     */
    private $plan;

    /**
     * @var User
     */
    private $user;

    /**
     * @var null|\Alaress_Date_Format
     */
    private $excludedAt;


    /**
     * @return Plan\Model
     */
    public function getPlan(): Plan\Model
    {
        return $this->plan;
    }


    /**
     * @param Plan\Model $plan
     * @return $this
     */
    public function setPlan(Plan\Model $plan): self
    {
        $this->plan = $plan;
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
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getExcludedAt(): ?Alaress_Date_Format
    {
        return $this->excludedAt;
    }


    /**
     * @param null|Alaress_Date_Format $excludedAt
     * @return $this
     */
    public function setExcludedAt(?Alaress_Date_Format $excludedAt): self
    {
        $this->excludedAt = $excludedAt;
        return $this;
    }
}

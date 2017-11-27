<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Participation;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Assessment\Participation\Plan as ParticipationPlan;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Assessment\Participation
 */
class Model implements ISoftDeletable
{

    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     *
     *
     * @var ParticipationPlan\Model
     */
    protected $participationPlan;

    /**
     * @var User
     */
    protected $user;


    /**
     * @return ParticipationPlan\Model
     */
    public function getParticipationPlan(): ParticipationPlan\Model
    {
        return $this->participationPlan;
    }

    /**
     * @param ParticipationPlan\Model $participationPlan
     */
    public function setParticipationPlan($participationPlan)
    {
        $this->participationPlan = $participationPlan;
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
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }

}
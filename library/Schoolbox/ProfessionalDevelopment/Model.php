<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var User
     */
    private $user;

    /**
     * Professional Development number
     *
     * @var int
     */
    private $number;

    /**
     * The state in which the teacher registered
     *
     * @var int
     */
    private $registrationState;


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
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->number;
    }


    /**
     * @param int $number
     * @return $this
     */
    public function setNumber(int $number)
    {
        $this->number = $number;
        return $this;
    }


    /**
     * @return int
     */
    public function getRegistrationState(): int
    {
        return $this->registrationState;
    }


    /**
     * @param int $registrationState
     * @return $this
     */
    public function setRegistrationState(int $registrationState)
    {
        $this->registrationState = $registrationState;
        return $this;
    }
}




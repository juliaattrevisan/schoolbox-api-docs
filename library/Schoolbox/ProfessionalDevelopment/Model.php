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
     * @var string
     */
    private $number;

    /**
     * The state in which the teacher registered
     *
     * @var string
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
     * @return string
     */
    public function getNumber(): string
    {
        return $this->number;
    }


    /**
     * @param string $number
     * @return $this
     */
    public function setNumber(?string $number)
    {
        $this->number = $number;
        return $this;
    }


    /**
     * @return string
     */
    public function getRegistrationState(): string
    {
        return $this->registrationState;
    }


    /**
     * @param string $registrationState
     * @return $this
     */
    public function setRegistrationState(?string $registrationState)
    {
        $this->registrationState = $registrationState;
        return $this;
    }
}




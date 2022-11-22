<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Alaress_Date_Format;
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
     * The date someone was accredited
     *
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $accreditedAt = null;

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return self
     */
    public function setUser(User $user): self
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
     * @return self
     */
    public function setNumber(?string $number): self
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
     * @return self
     */
    public function setRegistrationState(?string $registrationState): self
    {
        $this->registrationState = $registrationState;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getAccreditedAt(): ?Alaress_Date_Format
    {
        return $this->accreditedAt;
    }

    /**
     * @param Alaress_Date_Format|null $accreditedAt
     *
     * @return static
     */
    public function setAccreditedAt(?Alaress_Date_Format $accreditedAt): self
    {
        $this->accreditedAt = $accreditedAt;
        return $this;
    }
}

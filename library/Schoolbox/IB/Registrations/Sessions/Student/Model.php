<?php

/**
 * IB Registration Session Student Model
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use Schoolbox\IB\Registrations\Sessions\Model as SessionModel;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\IB\Registrations\Sessions\Student
 */
class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;

    /**
     * Registration status
     * @var int
     */
    private int $status = 0;

    /**
     * Registration session this student belongs to
     * @var SessionModel|null
     */
    private ?SessionModel $registrationSession = null;

    /**
     * Student user record
     * @var User|null
     */
    private ?User $user = null;

    /**
     * @return SessionModel|null
     */
    public function getRegistrationSession(): ?SessionModel
    {
        return $this->registrationSession;
    }

    /**
     * @param SessionModel|null $registrationSession
     * @return self
     */
    public function setRegistrationSession(?SessionModel $registrationSession): self
    {
        $this->registrationSession = $registrationSession;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return self
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return self
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }
}

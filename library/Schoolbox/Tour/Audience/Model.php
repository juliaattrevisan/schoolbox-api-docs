<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Tour\Audience;

use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Tour;

class Model
{
    /**
     * @var User|null
     */
    protected $moderator;

    /**
     * @var Tour\Model|null
     */
    protected $tour;

    /**
     * @var Role|null
     */
    protected $role;


    /**
     * @return null|Role
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param null|Role $role
     */
    public function setRole(Role $role)
    {
        $this->role = $role;
    }

    /**
     * @return null|User
     */
    public function getModerator()
    {
        return $this->moderator;
    }

    /**
     * @param null|User $moderator
     * @return $this
     */
    public function setModerator(User $moderator)
    {
        $this->moderator = $moderator;
        return $this;
    }

    /**
     * @return null|Tour\Model
     */
    public function getTour()
    {
        return $this->tour;
    }

    /**
     * @param null|Tour\Model $tour
     * @return $this
     */
    public function setTour(Tour\Model $tour)
    {
        $this->tour = $tour;
        return $this;
    }
}

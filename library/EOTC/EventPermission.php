<?php

/**
 * EventPermission
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\User;
use Schoolbox\Campus;

class EventPermission
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var int
     */
    private $id;

    /**
     * The user to whom this permissions/subscriptions applies
     *
     * @var User
     */
    private $user;

    /**
     * The campus to which this permissions/subscriptions applies
     *
     * @var Campus|null
     */
    private $campus;

    /**
     * The status to which this permissions/subscriptions applies
     *
     * @var EventStatus
     */
    private $status;

    /**
     * Is this user allowed to edit EOTC events in this status for this campus?
     *
     * @var bool
     */
    protected $allowed = false;

    /**
     * Is this user subscribed to receive messages for EOTC events when they
     * are switched to this status?
     *
     * @var bool
     */
    protected $subscribed = false;

    /* Getters/Setters
     */

    /**
     * @param Campus $campus
     * @return $this
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
        return $this;
    }

    /**
     * @return Campus|null
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param EventStatus $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return EventStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return bool
     */
    public function getAllowed()
    {
        return $this->allowed;
    }

    /**
     * @param  bool $allowed
     * @return $this
     */
    public function setAllowed($allowed)
    {
        $this->allowed = $allowed;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSubscribed()
    {
        return $this->subscribed;
    }

    /**
     * @param  bool $subscribed
     * @return $this
     */
    public function setSubscribed($subscribed)
    {
        $this->subscribed = $subscribed;
        return $this;
    }
}

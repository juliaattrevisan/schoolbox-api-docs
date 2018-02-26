<?php
/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Status;

use Schoolbox\User;
use Schoolbox\Tour;
use Schoolbox\Tour\Step;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;

class Model implements \JsonSerializable
{
    use CreatedAtDate;

    /** User has started the joyride */
    public const STATUS_STARTED = 'started';

    /** User has dismissed the joyride- and presumably never wants to see the damn thing again */
    public const STATUS_DISMISSED = 'dismissed';

    /** User has completed the joyride */
    public const STATUS_COMPLETED = 'completed';

    /** User has not interacted with this step/step in the joyride */
    public const STATUS_DISABLED = 'disabled';

    /**
     * @var User|null
     */
    protected $user;

    /**
     * @var Tour\Model|null
     */
    protected $tour;

    /**
     * @var String|null
     */
    protected $status;


    /**
     * @return null|String
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * @param null|String $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }


    /**
     * @return null|User
     */
    public function getUser()
    {
        return $this->user;
    }


    /**
     * @param null|User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
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
    public function setTour($tour)
    {
        $this->tour = $tour;
        return $this;
    }


    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'user'      => $this->getUser(),
            'status'    => $this->getStatus(),
            'tourStep'  => $this->getTour()
        ];
    }
}

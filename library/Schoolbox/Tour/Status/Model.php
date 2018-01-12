<?php
/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Status;

use Schoolbox\User;
use Schoolbox\Tour\Step;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;

class Model implements \JsonSerializable
{
    use CreatedAtDate;

    /** User has not interacted with this step/step in the joyride */
    const STATUS_UNACTIONED = 'unactioned';

    /** User has completed the joyride */
    const STATUS_COMPLETED = 'completed';

    /** User has dismissed the joyride- and presumably never wants to see the damn thing again */
    const STATUS_DISMISSED = 'dismissed';

    /**
     * @var User|null
     */
    protected $user;

    /**
     * @var String|null
     */
    protected $url;

    /**
     * @var Step\Model|null
     */
    protected $tourStep;

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
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return null|String
     */
    public function getUrl()
    {
        return $this->url;
    }


    /**
     * @param null|String $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }


    /**
     * @return null|Step\Model
     */
    public function getTourStep()
    {
        return $this->tourStep;
    }


    /**
     * @param null|Step\Model $tourStep
     */
    public function setTourStep($tourStep)
    {
        $this->tourStep = $tourStep;
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
            "user"      => $this->getUser(),
            "url"       => $this->getUrl(),
            "status"    => $this->getStatus(),
            "tourStep"  => $this->getTourStep()
        ];
    }
}

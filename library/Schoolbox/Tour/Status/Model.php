<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Status;

use Schoolbox\User;
use Schoolbox\Tour\Step;

class Model
{

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
    }
}
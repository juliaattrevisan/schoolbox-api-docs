<?php

namespace Schoolbox\PTO;

use Schoolbox\User;

/**
 * Loader
 *
 * Loads Parent Teacher Online authentication stuff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Loader
{
    /**
     * PTO access url
     *
     * @var string
     */
    const REMOTE_URL = "https://bookings.parentteacheronline.com.au?school=[school]&Type=[type]&P1=[username]&P2=[epoch]&P3=[hash]";

    private $user;
    private $epoch;
    private $school;

    /**
     * Loader constructor.
     *
     * @param User $user
     * @param string $epoch
     * @param string $school
     */
    public function __construct(User $user, $epoch, $school)
    {
        $this->setUser($user);
        $this->setEpoch($epoch);
        $this->setSchool($school);
    }

    /**
     * Try to work out user type
     *
     * @return string
     */
    private function determineUserType()
    {
        /* Student/Parent
         */
        if ($this->getUser()->getRole()->isStudent() || $this->getUser()->getRole()->isParent()) {
            return 'S';
        }
        /* Teacher
         */
        elseif ($this->getUser()->getRole()->isStaff()) {
            return 'T';
        }
        /* Administrator
         */
        elseif ($this->getUser()->isSuperuser()) {
            return 'A';
        }

        /* Leave it up to PTO to work out
         */
        return '*';
    }

    /**
     * Generate hash for all the params
     */
    private function generateHash()
    {
        $ptoKey = \Config::get('pto_key');

        return hash('sha256', "{$this->getSchool()}{$this->getUser()->getUsername()}{$this->getEpoch()}{$ptoKey}");
    }

    /* Getters/Setters
     */

    /**
     * @return mixed
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    private function getEpoch()
    {
        return $this->epoch;
    }

    /**
     * @param mixed $epoch
     */
    private function setEpoch($epoch)
    {
        $this->epoch = $epoch;
    }

    /**
     * @return mixed
     */
    private function getSchool()
    {
        return $this->school;
    }

    /**
     * @param mixed $school
     */
    private function setSchool($school)
    {
        $this->school = $school;
    }




}
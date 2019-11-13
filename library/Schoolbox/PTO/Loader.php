<?php
/**
 * Loader
 *
 * Loads Parent Teacher Online authentication stuff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\PTO;

use Schoolbox\IConfigLoader;
use Schoolbox\User;

class Loader
{
    /**
     * PTO access url
     *
     * @var string
     */
    const REMOTE_URL = "https://bookings.parentteacheronline.com.au/";

    /** @var string */
    private $ptoKey;

    /** @var string */
    private $school;

    /** @var User */
    private $user;

    /**
     * Loader constructor
     *
     * @param IConfigLoader $configLoader
     * @param User $user
     */
    public function __construct(IConfigLoader $configLoader, User $user)
    {
        $this->setPtoKey($configLoader->get('pto_key'));
        $this->setSchool($configLoader->get('pto_school'));
        $this->setUser($user);
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
            return 'P';
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
     *
     * @param string $epoch
     * @return string
     */
    private function generateHash($epoch)
    {
        return hash('sha256', "{$this->getSchool()}{$this->getPTOLogin()}{$epoch}{$this->getPtoKey()}");
    }

    /**
     * Generate the remote url
     *
     * @return string
     */
    public function generateRemoteUrl()
    {
        $epoch = date('U');

        $urlArgs = [
            'School' => $this->getSchool(),
            'Type' => $this->determineUserType(),
            'P1' => $this->getPTOLogin(),
            'P2' => $epoch,
            'P3' => $this->generateHash($epoch)
        ];

        $remoteUrl = self::REMOTE_URL .'?'. http_build_query($urlArgs);

        return $remoteUrl;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    private function getPtoKey()
    {
        return $this->ptoKey;
    }

    /**
     * @param string $ptoKey
     * @return $this
     */
    private function setPtoKey($ptoKey)
    {
        $this->ptoKey = $ptoKey;
        return $this;
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    private function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    private function getSchool()
    {
        return $this->school;
    }

    /**
     * @param string $school
     * @return $this
     */
    private function setSchool($school)
    {
        $this->school = $school;
        return $this;
    }


    /**
     * @return string
     */
    protected function getPTOLogin()
    {
        return $this->getUser()->getUsername();
    }
}

<?php

namespace Schoolbox\PTO;

use Schoolbox\User;
use Schoolbox\Config\Loader as ConfigLoader;
use Zend\Uri\Uri;

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

    /** @var ConfigLoader */
    private $configLoader;

    /** @var User */
    private $user;

    /** @var string */
    private $epoch;

    /** @var string */
    private $school;

    /**
     * Loader constructor.
     *
     * @param ConfigLoader $configLoader
     * @param User $user
     * @param string $epoch
     * @param string $school
     */
    public function __construct(ConfigLoader $configLoader, User $user, $epoch, $school)
    {
        $this->setConfigLoader($configLoader);
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
        return hash('sha256', "{$this->getSchool()}{$this->getUser()->getUsername()}{$this->getEpoch()}{$this->getConfigLoader()->get('pto_key')}");
    }

    /**
     * Generate the remote url
     *
     * @throws \DomainException
     * @return string
     */
    public function generateRemoteUrl()
    {
        $remoteUrl = strtr(self::REMOTE_URL, [
            'epoch' => $this->getEpoch(),
            'school' => $this->getSchool(),
            'username' => $this->getUser()->getUsername(),
            'type' => $this->determineUserType(),
            'hash' => $this->generateHash()
        ]);

        $url = new Uri($remoteUrl);

        if (!$url->isValid()) {
            throw new \DomainException('Invalid remote url generated');
        }

        return $remoteUrl;
    }
    
    /* Getters/Setters
     */

    /**
     * @return ConfigLoader
     */
    private function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * @param ConfigLoader $configLoader
     */
    private function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    private function getEpoch()
    {
        return $this->epoch;
    }

    /**
     * @param string $epoch
     */
    private function setEpoch($epoch)
    {
        $this->epoch = $epoch;
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
     */
    private function setSchool($school)
    {
        $this->school = $school;
    }
}
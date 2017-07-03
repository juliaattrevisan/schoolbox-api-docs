<?php
/**
 * Loader
 *
 * Loads Parent Teacher Online authentication stuff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\PTO;

use DomainException;
use Schoolbox\User;
use Schoolbox\Config\Loader as ConfigLoader;
use Zend\Uri\Uri;

class Loader
{
    /**
     * PTO access url
     *
     * @var string
     */
    const REMOTE_URL = "https://bookings.parentteacheronline.com.au?school=[school]&Type=[type]&P1=[username]&P2=[epoch]&P3=[hash]";

    /** @var string */
    private $ptoKey;

    /** @var string */
    private $school;

    /** @var User */
    private $user;

    /**
     * Loader constructor
     *
     * @param ConfigLoader $configLoader
     * @param User $user
     */
    public function __construct(ConfigLoader $configLoader, User $user)
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
     *
     * @param string $epoch
     * @return string
     */
    private function generateHash($epoch)
    {
        return hash('sha256', "{$this->getSchool()}{$this->getUser()->getUsername()}{$epoch}{$this->getPtoKey()}");
    }

    /**
     * Generate the remote url
     *
     * @throws DomainException
     * @return string
     */
    public function generateRemoteUrl()
    {
        $epoch = date('U');

        $remoteUrl = strtr(self::REMOTE_URL, [
            'epoch' => $epoch,
            'school' => $this->getSchool(),
            'username' => $this->getUser()->getUsername(),
            'type' => $this->determineUserType(),
            'hash' => $this->generateHash($epoch)
        ]);

        $url = new Uri($remoteUrl);

        if (!$url->isValid()) {
            throw new DomainException('Invalid remote url generated');
        }

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
    private function getUser()
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
}
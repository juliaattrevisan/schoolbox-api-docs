<?php
/**
 * Account
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Common;


class Account {

    /**
     * NetworkLogin equivalent
     *
     * @var string
     */
    private $login = '';

    /**
     * This account is active
     *
     * @var bool
     */
    private $active = false;

    /**
     * Constructor
     *
     * Immutable
     *
     * @param $login
     * @param $active
     */
    public function __construct($login, $active) {
        $this->setLogin($login);
        $this->setActive($active);
    }

    /* Getters/Setters
     */

    /**
     * Set active
     *
     * @param boolean $active
     */
    private function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set login
     *
     * @param string $login
     */
    private function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * Get login
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }
}
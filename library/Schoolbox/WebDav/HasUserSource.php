<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav;

use Schoolbox\User;

/**
 * User source trait for WebDAV classes.
 *
 * This is required for the WebDAV file and subject servers, because they
 * need to be constructed before a user has been determined.
 */
trait HasUserSource
{
    /**
     * An object which returns the currently logged-in user.
     *
     * @var callable
     */
    private $userSource;

    /**
     * @var User
     */
    private $user = null;

    /**
     * Convenience method for getting user out of its source.
     *
     * @return User
     */
    private function getUser()
    {
        if (!isset($this->user)) {
            $this->user = call_user_func($this->getUserSource());
        }
        return $this->user;
    }

    /**
     * @return callable
     */
    private function getUserSource()
    {
        return $this->userSource;
    }

    /**
     * @param callable $userSource
     * @return static
     */
    private function setUserSource($userSource)
    {
        $this->userSource = $userSource;
        return $this;
    }
}

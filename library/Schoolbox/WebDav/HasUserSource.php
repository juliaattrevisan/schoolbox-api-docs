<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @throws \InvalidArgumentException if the user source didn't return a user
     */
    private function retrieveUserFromSource()
    {
        if (!isset($this->user)) {
            $this->user = call_user_func($this->getUserSource());
        }

        // This MUST return a user object
        if (!$this->user instanceof User) {
            throw new \InvalidArgumentException("User source did not return a \\Schoolbox\\User object when invoked");
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
    private function setUserSource(callable $userSource)
    {
        $this->userSource = $userSource;
        return $this;
    }
}

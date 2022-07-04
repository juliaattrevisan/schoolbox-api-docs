<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\NotAuthenticated as SchoolboxNotAuthenticatedException;
use Schoolbox\External\SubjectMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Authenticator
{
    /**
     * Are you authenticated?
     *
     * @var bool
     */
    private $authenticated = false;

    /* Authentication Functions */

    /**
     * Encapsulating authentication method.
     *
     * @param string $username
     * @param string $password
     * @return User
     * @throws SchoolboxAccessDeniedException
     */
    public function authenticate(string $username, string $password): User
    {
        $s    = new Session();
        $user = $s->authenticate($username, $password);
        if ($user instanceof User) {
            $this->authenticated = true;
        }

        // if successful, go ahead and create a session
        if ($this->isAuthenticated()) {
            $this->createSession($user->getUsername());
            return $user;
        }
        throw new SchoolboxAccessDeniedException('Username or password is incorrect');
    }


    /* Session Functions */

    /**
     * Destroys current session, and starts one for the user with this username.
     *
     * This method no longer checks if `$this->isAuthenticated` is true: it
     * assumes that the user has already been authenticated as a valid user
     * somehow.
     *
     * @param string                $username
     * @param User\Token\Model|null $userToken
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     * @throws SchoolboxNotAuthenticatedException if session couldn't be created
     */
    public function createSession(string $username, ?User\Token\Model $userToken = null): bool
    {
        $this->destroySession();

        // start by creating a desktop session, then we will add the mobile elements into it
        if (session_start()) {
            $session = new Session();
            if (!$session->create($username, $userToken ? $userToken->getJwtId() : null)) {
                throw new SchoolboxNotAuthenticatedException('Cannot create session, not authenticated');
            }

            return true;
        }
        return false;
    }

    /**
     * Append session
     *
     * @param int $userID
     * @return bool
     */
    public function appendSession(int $userID): bool
    {
        /**
         * @var UserMapper $userMapper
         */
        $userMapper = UserMapper::getInstance();

        try {
            // yep, you ACTUALLY have to do this otherwise hydrations don't get done
            $user = $userMapper->findById($userID);

            /**
             * Map subjects onto the user object retrieved, and dump into session
             *
             * @var SubjectMapper $subjectMapper
             */
            $subjectMapper = SubjectMapper::getInstance();
            $user->setSubjectCollection($subjectMapper->findAllForStudent($user));
            $this->setSessionUser($user);

            // indicate that session creation was successful
            return true;
        } catch (Schoolbox\Mapper\Exception $e) {
            // noop
        }

        return false;
    }

    /**
     * Set a session var
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setSessionVar(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session var
     *
     * @param string $key
     * @return mixed
     */
    public function getSessionVar(string $key)
    {
        return $_SESSION[$key];
    }

    /**
     * Set session vars with a key/value array
     *
     * @param iterable $vars
     */
    public function setSessionVars(iterable $vars): void
    {
        foreach ($vars as $k => $v) {
            $_SESSION[$k] = $v;
        }
    }

    /* SESSION USER */
    /* There are 3 mechanisms for these methods:
     *   1. Store User object hierarchy in $_SESSION.
     *      In high-traffic servers, this will put strain on the system
     *   2. Store user ID in $_SESSION
     *      This will require retrieval from the DB on every pageload, which is potentially faster than the
     *      deserialisation of the User object anyway.
     *   3. Consider using a datastore (let's not go there).
     *
     *   For more on this topic:  http://stackoverflow.com/questions/132194/php-storing-objects-inside-the-session
     */

    /**
     * @param User $user
     */
    public function setSessionUser(User $user): void
    {
        $_SESSION['user'] = $user;
        $this->touch();
    }

    /**
     * @return User|null
     */
    public function getSessionUser(): ?User
    {
        $this->touch();
        return $_SESSION['user'];
    }

    /**
     * Update the latest touch of the site by the user to being now
     */
    private function touch(): void
    {
        $_SESSION['lasttime'] = time();
    }

    public function destroySession(): void
    {
        @session_destroy();
    }

    /**
     * Returns whether or not the current request is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        // explicitly define boolean
        return isset($_SESSION['user']) || $this->authenticated;
    }

    /**
     * Attempt to retrieve the IP of the client in a proxy-sensitive manner
     *
     * @return string|null
     */
    public function getIP(): ?string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = null;
        }

        return $ip;
    }
}

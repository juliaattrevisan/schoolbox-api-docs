<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\External\SubjectMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\NotAuthenticated as SchoolboxNotAuthenticatedException;

class Authenticator {

    /**
     * Are you authentiated?
     *
     * @var bool
     */
    private $authenticated = false;

    /**
     * Authenticator constructor.
     */
    public function __construct() {

    }

    /* Authentication Functions */

    /**
     * Encapsulating authentication method. Takes password by reference because it will unset it at the end of its use
     *
     * @param $username
     * @param $password
     * @param bool $remember
     * @throws SchoolboxAccessDeniedException
     */
    public function authenticate($username, $password, $remember = false) {
        $s = new Session();
        $user = $s->authenticate($username, $password);
        if($user instanceof User) {
            $this->authenticated = true;
        }

        // if successful, go ahead and create a session
        if ($this->isAuthenticated()) {
            $this->createSession($user->getUsername(), $remember);
        } else {
            throw new SchoolboxAccessDeniedException('Username or password is incorrect');
        }
    }

    /**
     * Explicitly deauthenticates any existing authentication and retests
     * @param $username
     * @param $password
     * @param bool $remember
     */
    public function reauthenticate($username, $password, $remember = false) {
        $this->authenticated = false;
        $this->authenticate($username, $password, $remember);
    }

    /* Session Functions */

    /**
     * Start session
     *
     * @param $username
     * @param bool $remember
     * @return bool
     * @throws SchoolboxNotAuthenticatedException
     */
    public function createSession($username, $remember = false) {
        // do nothing if I don't know you
        if (!$this->isAuthenticated()) {
            throw new SchoolboxNotAuthenticatedException('Cannot create session, not authenticated');
        }

        $this->destroySession();

        // start by creating a desktop session, then we will add the mobile elements into it
        if (session_start()) {
            $session = new Session();
            if(!$session->create($username, $remember)) {
                throw new SchoolboxNotAuthenticatedException('Cannot create session, not authenticated');
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Append session
     *
     * @param $userID
     * @return bool
     */
    public function appendSession($userID) {

        /**
         * @var UserMapper $userMapper
         */
        $userMapper = UserMapper::getInstance();

        try {
            // yep, you ACTUALLY have to do this otherwise hydrations don't get done
            $user = $userMapper->findById($userID);

            /**
             * Map subjects onto the user object retrieved, and dump into session
             * @var SubjectMapper $subjectMapper
             */
            $subjectMapper = SubjectMapper::getInstance();
            $user->setSubjectCollection($subjectMapper->findAllForStudent($user));
            $this->setSessionUser($user);

            // indicate that session creation was successful
            return true;
        }
        catch (Schoolbox\Mapper\Exception $e) {}

        return false;
    }

    /**
     * Set a session var
     *
     * @param $key
     * @param $value
     */
    public function setSessionVar($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session var
     *
     * @param $key
     * @return mixed
     */
    public function getSessionVar($key) {
        return $_SESSION[$key];
    }

    /**
     * Set session vars with a key/value array
     *
     * @param $vars
     */
    public function setSessionVars($vars) {
        foreach($vars as $k => $v)
            $_SESSION[$k] = $v;
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
    public function setSessionUser($user) {
        // $_SESSION['user'] = $user->get('uid');
        // -- or --
        $_SESSION['user'] = $user;
        $this->touch();
    }

    /**
     * @return User
     */
    public function getSessionUser() {
        // $uMap = new GizaUser_DataMapper();
        // return $uMap->read($_SESSION['user']);
        $this->touch();
        // -- or --
        return $_SESSION['user'];
    }

    /**
     * Update the latest touch of the site by the user to being now
     */
    private function touch() {
        $_SESSION['lasttime'] = time();
    }

    public function destroySession() {
        @session_destroy();
    }

    /**
     * Returns whether or not the current request is authenticated
     * @return bool
     */
    public function isAuthenticated() {
        // explicitly define boolean
        return (isset($_SESSION['user']) || $this->authenticated) ? true : false;
    }

    /**
     * Attempt to retrieve the IP of the client in a proxy-sensitive manner
     * @return string
     */
    public function getIP() {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else if(isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        else {
            $ip = null;
        }

        return $ip;
    }

}

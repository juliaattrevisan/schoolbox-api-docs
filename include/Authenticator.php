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

class Authenticator {

    private $authenticated = false;

    public function __construct() {

    }

    /* Authentication Functions */

    /**
     * Encapsulating authentication method. Takes password by reference because it will unset it at the end of its use
     * @param $username
     * @param $password
     * @param string $method
     */
    public function authenticate($username, &$password) {

        $s = new Session();
        $this->authenticated = $s->authenticate($username, $password);

        // for security, unset password that was delivered
        unset($password);

        // if successful, go ahead and create a session
        if($this->isAuthenticated())
            $this->createSession($username);
        else
            throw new Exception_Schoolbox_AccessDenied('Username or password is incorrect.');
    }

    /**
     * Explicitly deauthenticates any existing authentication and retests
     * @param $username
     * @param $password
     * @param string $method
     */
    public function reauthenticate($username, &$password, $method = 'all') {
        $this->authenticated = false;
        $this->authenticate($username, $password, $method);
    }

    /* Session Functions */

    public function createSession($username) {
        // do nothing if I don't know you
        if(!$this->isAuthenticated())
            throw new Exception_Schoolbox_NotAuthenticated('Cannot create session, not authenticated.');

        $this->destroySession();

        // start by creating a desktop session, then we will add the mobile elements into it
        $session = new Session();
        $session->create($username);

        return session_start() ? $this->appendSession($username) : false;
    }

    public function appendSession($username) {
        //
        // Mobile not enabled? Just pretend we added the mobile portion of the session.
        if (!Cfg::get('mobile_enabled')) {
            return true;
        }

	    /**
	     *
	     * @var UserMapper $userMapper
	     */
	    $userMapper = UserMapper::getInstance();

	    $user = $userMapper->findByColumn('username', $username)->first();

        if($user instanceof User) {

	        // yep, you ACTUALLY have to do this otherwise hydrations don't get done
	        $user = $userMapper->findById($user->getId());

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

        return false;
    }

// oqhd37hs

    public function setSessionVar($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function getSessionVar($key) {
        return $_SESSION[$key];
    }

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

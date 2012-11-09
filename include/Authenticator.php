<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
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
    public function authenticate($username, &$password, $method = 'all') {
        // authentication methods in order of usage
        $authMethods = array('sso', 'ldap', 'mysql');

        // test all methods in order
        if($method == 'all') {
            foreach($authMethods as $test) {
                // if the previous method succeeded, don't bother trying to authenticate
                if($this->isAuthenticated()) {
                    return;
                }
                $func = 'auth' . strtoupper($test);
                if(method_exists($this, $func)) {
                    $this->$func($username, $password);
                }
            }
        }

        // test a single method
        elseif(in_array($method, $authMethods)) {
            if(!$this->isAuthenticated()) {
                $func = 'auth' . strtoupper($method);
                if(method_exists($this, $func)) {
                    $this->$func($username, $password);
                }
            }
        }

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

    /**
     * Private method for testing Single Sign-On authentication
     * Sets $this->authenticated = true upon success
     * @param $username
     * @param $password
     */
    private function authSSO($username, &$password) {

    }

    /**
     * Private method for testing LDAP authentication
     * Sets $this->authenticated = true upon success
     * @param $username
     * @param $password
     */
    private function authLDAP($username, &$password) {

    }

    /**
     * Private method for testing MySQL authentication
     * Sets $this->authenticated = true upon success
     * @param $username
     * @param $password
     */
    private function authMYSQL($username, &$password) {
        $query = "SELECT user.id
                  FROM user
                  WHERE username = '". mysql_real_escape_string($username)."'
                  AND password = PASSWORD('".mysql_real_escape_string($password)."')";

        $db = DeprecatedDatabase::getInstance();
        // DeprecatedDatabank
        // DeprecatedUser
        $db->query($query);
        $result = $db->countRows();

        if($result == 1)
            $this->authenticated = true;
    }


    /* Session Functions */

    public function createSession($username) {
        // do nothing if I don't know you
        if(!$this->isAuthenticated())
            throw new Exception_Schoolbox_NotAuthenticated('Cannot create session, not authenticated.');

        $this->destroySession();

        $uMap = new GizaUser_DataMapper();
        $user = $uMap->read(array('username' => $username))->get();

        if(session_start() && count($user)) {

            $this->setSessionUser($user);

            if(Config::get('mail_enable') == 0 ||
                $user->get('mail_handler') ||
                ( $user->getGroupType() == 'parent' && $user->get('local_email'))
            ) {
                $sessions['mailhandle'] = "mailto:";
            }
            else {
                $sessions['mailhandle'] = "/mail/createMail.php?replyto=";
            }

            /*
             * Not sure this needs to be hydrated yet
            $user->getSubjects();
            */

            // commit the array to the $_SESSION superglobal
            $this->setSessionVars($sessions);
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
     * @param GizaUser $user
     */
    public function setSessionUser($user) {
        // $_SESSION['user'] = $user->get('uid');
        // -- or --
        $_SESSION['user'] = $user;


        // TODO refactor the system to make the following
        $uMap = new GizaUser_DataMapper();
        $uMap->hydrateCampus($user);

        $_SESSION['id'] = $user->get('uid');
        foreach($user->get('campus')->asArray() as $c) {
            $_SESSION['campus'][$c->get('campus')] = $c->get('campus');
        }
        $_SESSION['campus'] = array_values($_SESSION['campus']);
        $_SESSION['gid'] = $user->get('group_id');
    }

    /**
     * @return GizaUser
     */
    public function getSessionUser() {
        // $uMap = new GizaUser_DataMapper();
        // return $uMap->read($_SESSION['user']);
        // -- or --
        return $_SESSION['user'];
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

<?php
/**
 * Basic authentication scheme for WebDAV access to Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class DAV_Auth_Basic extends Sabre\DAV\Auth\Backend\AbstractBasic
{
    /**
     * Validates a username and password
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function validateUserPass($username, $password)
    {
        $auth = new Authenticator();
        try {
            $auth->authenticate($username, $password);
            return true;
        } catch (\Exception_Schoolbox $e) {
            return false;
        }
    }
}
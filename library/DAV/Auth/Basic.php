<?php

/**
 * Basic authentication scheme for WebDAV access to Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\Exception\Exception as SchoolboxException;

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
        } catch (SchoolboxException $e) {
            return false;
        }
    }
}

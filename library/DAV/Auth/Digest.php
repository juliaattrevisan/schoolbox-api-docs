<?php

/* Schoolbox implementation of digest auth for Sabre WebDAV server */

class DAV_Auth_Digest extends Sabre\DAV\Auth\Backend\AbstractDigest {

    function getDigestHash($realm, $username) {
        $userHandler = new User;
        $user = $userHandler->getByUsername($username);
        if ($user['hash']) {
            return $user['hash'];
        } else {
            return false;
        }
    }

    public function check(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
    {
        $result = parent::check($request, $response);
        list($authenticated, $username) = $result;
        if ($authenticated) {
            $username = preg_replace('/^'.preg_quote($this->principalPrefix, '/').'/', '', $username);
            $session  = new Session();
            $session->create($username);
        }
        return $result;
    }
}
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

    public function authenticate(Sabre\DAV\Server $server, $realm) {
        if (parent::authenticate($server, $realm)) {
            $session = new Session();
            $session->create($this->getCurrentUser());
            return true;
        }

        return false;
    }
}
<?php

/* Schoolbox implementation of digest auth for Sabre WebDAV server */

class DAV_Digest extends Sabre_DAV_Auth_Backend_AbstractDigest {

    function getDigestHash($realm, $username) {
        $userHandler = new User;

        $user = $userHandler->getByUsername($username);

        return $user['hash'];

    }

    public function authenticate(Sabre_DAV_Server $server, $realm) {
        if (parent::authenticate($server, $realm)) {
            $session = new Session();
            $session->create($this->getCurrentUser());
            return true;
        }

        return false;
    }
}
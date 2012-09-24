<?php

/* Schoolbox implementation of digest auth for Sabre WebDAV server */

class DAV_Digest extends Sabre_DAV_Auth_Backend_AbstractDigest {

    function getDigestHash($realm, $username) {
        $userHandler = new User;

        $user = $userHandler->getByUsername($username);

        return $user['hash'];

    }
}
<?php
/**
 * StudentIterator
 *
 * Custom implementation for chub
 * 
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class GuardianIterator extends IteratorIterator
{
    /**
     * Return the current element
     *
     * @return User\Guardian
     */
    public function current()
    {
        $currentResult = $this->getInnerIterator()->current();

        $guardian       = new User\Guardian();
        $guardian
            ->setId($currentResult['ID'])
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['FirstName'])
            ->setLastname($currentResult['Surname'])
        ;

        /* Account
         */
        $guardian
            ->getAccount()
            ->setLogin($currentResult['UserName'])
        ;

        /* Email
         */
        $email = new User\Common\Email();
        $email->setAddress($currentResult['email']);
        $guardian->addEmail($email);

        /* Find assorted children
         *
         * For some reason I can't run a stored procedure while
         * I am currently iterating the resultset of a previous
         * stored procedure, like this scenario
         *
         * Calls to getEnrolledStudents() returns false while iterating
         * the resultset of another stored proc
         *
         * Orvad 6067 has one child
         *
         * This forces you to have Skip Parents With No Enrolled Children
         * custom hydrator to be off
         *
        $userHandler = new \External_User();
        $children    = $userHandler->getParent((int)$currentResult['ID']);
         */

        return $guardian;
    }
}
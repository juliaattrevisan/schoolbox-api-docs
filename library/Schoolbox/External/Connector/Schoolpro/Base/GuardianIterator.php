<?php
/**
 * StudentIterator
 *
 * Custom implementation for chub
 * 
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class GuardianIterator extends IteratorIterator
{
    /**
     * Return the current element
     *
     * @return User\Student
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

        return $guardian;
    }
}
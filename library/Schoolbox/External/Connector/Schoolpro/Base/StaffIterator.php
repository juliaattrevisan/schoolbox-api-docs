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

use Schoolbox\External\Normalise\User;
use Schoolbox\Sync\Iterator\Staff;

class StaffIterator extends Staff
{
    /**
     * Return the current element
     *
     * @return User\Staff
     */
    public function current()
    {
        $currentResult = $this->getInnerIterator()->current();
        $staff         = new User\Staff();
        $staff
            ->setId($currentResult['ID'])
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['FirstName'])
            ->setLastname($currentResult['Surname'])
        ;

        $staff
            ->getMeta()
            ->getCategory()
            ->setType($currentResult['StaffCategory'])
        ;

        /* Account
         */
        $staff
            ->getAccount()
            ->setLogin($currentResult['UserName'])
            ->setActive(1)
        ;

        /* Assign emails
         */
        if (strlen($currentResult['email'])) {
            $email = new User\Common\Email();
            $email
                ->setAddress($currentResult['email']);

            $staff->addEmail($email);
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setCode($currentResult['CampusID'])
        ;

        $staff
            ->getCampus()
            ->add($campus)
        ;

        return $staff;
    }
}
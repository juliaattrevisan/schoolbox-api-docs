<?php
/**
 * Iterator over a `External_UserInterface->getStaffUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Staff` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class Staff extends IteratorIterator
{
    /**
     * Return the current element
     *
     * @return User\Staff
     */
    public function current()
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getStaffUserAccountInfo)
        $currentResult = $this->getInnerIterator()->current();
        $staff         = new User\Staff();
        $staff
            ->setId($currentResult['staff_id'])
            ->setTitle($currentResult['staff_title'])
            ->setPreferredName($currentResult['staff_preferred_name'])
            ->setLastname($currentResult['staff_surname'])
            ->getMeta()
            ->setDepartment($currentResult['staff_department'])
        ;

        $staff
            ->getMeta()
            ->getCategory()
            ->setType($currentResult['staff_category_type'])
        ;

        /* Account
         */
        $staff
            ->getAccount()
            ->setLogin($currentResult['network_login'])
            ->setActive($currentResult['active_flag'])
        ;

        /* Email
         */
        $email1 = new User\Common\Email();
        $email1
            ->setAddress($currentResult['email'])
        ;

        $email2 = new User\Common\Email();
        $email2
            ->setAddress($currentResult['occup_email'])
        ;

        foreach (array($email1, $email2) as $currentEmail) {
            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $staff->addEmail($currentEmail);
            }
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setName($currentResult['staff_campus'])
        ;

        $staff
            ->getCampus()
            ->add($campus)
        ;
        return $staff;
    }
}

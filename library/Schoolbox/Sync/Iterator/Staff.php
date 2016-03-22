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
        $currentResult = parent::current();
        $staff         = new User\Staff();
        $staff
            ->setId($currentResult['staff_id'])
            ->setTitle($currentResult['staff_title'])
            ->setPreferredName($currentResult['staff_preferred_name'])
            ->setGivenName($currentResult['staff_given_name'])
            ->setLastname($currentResult['staff_surname'])
            ->getMeta()
            ->setPosition($currentResult['staff_position'])
            ->setDepartment($currentResult['staff_department'])
        ;

        $staff
            ->getMeta()
            ->getCategory()
            ->setCode($currentResult['staff_category'])
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
        $email = array();
        if(substr_count($currentResult['email'],';') > 0){
            $emailList = explode(';',$currentResult['email']);
            foreach ($emailList as $anEmail) {
                $email[] = $anEmail;
            }
        }
        else {
            $email[] = $currentResult['email'];
        }

        if(substr_count($currentResult['occup_email'],';') > 0){
            $emailList = explode(';',$currentResult['occup_email']);
            foreach ($emailList as $anEmail) {
                $email[] = $anEmail;
            }
        }
        else {
            $email[] = $currentResult['occup_email'];
        }

        foreach ($email as $key => $anEmail) {
            $currentEmail = new User\Common\Email();
            $currentEmail->setAddress($anEmail);

            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $staff->addEmail($currentEmail);
            }
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setCode($currentResult['staff_campus'])
        ;

        $staff
            ->getCampus()
            ->add($campus)
        ;
        return $staff;
    }
}

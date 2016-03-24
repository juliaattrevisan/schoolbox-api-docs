<?php
/**
 * Iterator over a `External_UserInterface->getStudentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Student` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class Student extends IteratorIterator
{
    /**
     * Return the current element
     *
     * @return User\Student
     */
    public function current()
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getStudentUserAccountInfo)
        $currentResult = parent::current();
        $student       = new User\Student();
        $student
            ->setId($currentResult['student_id'])
            ->setTitle($currentResult['student_title'])
            ->setGivenName($currentResult['student_given_name'])
            ->setPreferredName($currentResult['student_preferred'])
            ->setLastname($currentResult['student_surname'])
            ->setGender($currentResult['student_gender'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['student_year_level'])
        ;

        $student
            ->getHouse()
            ->setCode($currentResult['student_house'])
        ;

        $student
            ->getAccount()
            ->setLogin($currentResult['network_login'])
        ;

        /* Assign emails
         */
        $email1 = new User\Common\Email();
        $email1
            ->setAddress($currentResult['student_email']);

        $email2 = new User\Common\Email();
        $email2
            ->setAddress($currentResult['student_occup_email']);

        foreach (array($email1, $email2) as $currentEmail) {
            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $student->addEmail($currentEmail);
            }
        }

        /* Boarding House should be set if it has a value
         * You can't actually rely on StudentBoarder field, because that
         * is not strictly yes/no; sometimes it contains B, W, D, etc.
         * It's really a Student Boarder Type, more than anything
         */
        if (strlen($currentResult['student_boarding_house'])) {
            $boardingHouse = new User\Common\BoardingHouse();
            $boardingHouse
                ->setCode($currentResult['student_boarding_house'])
            ;

            $student
                ->setBoardingHouse($boardingHouse)
            ;
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setCode($currentResult['student_campus'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;
        return $student;
    }
}

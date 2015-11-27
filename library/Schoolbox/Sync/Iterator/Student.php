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
        $currentResult = $this->getInnerIterator()->current();
        $student       = new User\Student();
        $student
            ->setId($currentResult['student_id'])
            ->setTitle($currentResult['student_title'])
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
            ->setName($currentResult['student_house'])
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

        if ($currentResult['student_boarder'] == 'Y') {
            $boardingHouse = new User\Common\BoardingHouse();
            $boardingHouse
                ->setName($currentResult['student_boarding_house'])
            ;

            $student
                ->setBoardingHouse($boardingHouse)
            ;
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setName($currentResult['student_campus'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;
        return $student;
    }
}

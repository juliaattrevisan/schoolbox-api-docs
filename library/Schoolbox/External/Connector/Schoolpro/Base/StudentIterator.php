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
use Schoolbox\Sync\Iterator\Student;

class StudentIterator extends Student
{
    /**
     * Return the current element
     *
     * @return User\Student
     */
    public function current()
    {
        $currentResult = $this->getInnerIterator()->current();
        $student       = new User\Student();
        $student
            ->setId($currentResult['ID'])
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['FirstName'])
            ->setLastname($currentResult['Surname'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['LevelID'])
        ;

        $student
            ->getHouse()
            ->setCode($currentResult['HouseID'])
        ;

        $student
            ->getAccount()
            ->setLogin($currentResult['UserName'])
        ;

        /* Assign emails
         */
        if (strlen($currentResult['email'])) {
            $email = new User\Common\Email();
            $email
                ->setAddress($currentResult['email']);

            $student->addEmail($email);
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
            ->setCode($currentResult['CampusID'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;

        return $student;
    }
}
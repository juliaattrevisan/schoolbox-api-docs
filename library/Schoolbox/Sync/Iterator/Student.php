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
            ->setId($currentResult['StudentID'])
            ->setTitle($currentResult['StudentTitle'])
            ->setPreferredName($currentResult['StudentPreferred'])
            ->setLastname($currentResult['StudentSurname'])
            ->setGender($currentResult['StudentGender'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['StudentYearLevel'])
        ;

        $student
            ->getHouse()
            ->setName($currentResult['StudentHouse'])
        ;

        $student
            ->getAccount()
            ->setLogin($currentResult['NetworkLogin'])
        ;

        /* Assign emails
         */
        $email1 = new User\Common\Email();
        $email1
            ->setAddress($currentResult['StudentEmail']);

        $email2 = new User\Common\Email();
        $email2
            ->setAddress($currentResult['StudentOccupEmail']);

        foreach (array($email1, $email2) as $currentEmail) {
            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $student->addEmail($currentEmail);
            }
        }

        if ($currentResult['StudentBoarder'] == 'Y') {
            $boardingHouse = new User\Common\BoardingHouse();
            $boardingHouse
                ->setName($currentResult['StudentBoardingHouse'])
            ;

            $student
                ->setBoardingHouse($boardingHouse)
            ;
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setName($currentResult['StudentCampus'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;
        return $student;
    }
}

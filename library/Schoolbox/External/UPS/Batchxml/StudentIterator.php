<?php
/**
 * StudentIterator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\UPS\Batchxml;

use IteratorIterator;
use Schoolbox\External\Normalise\User;
use Schoolbox\Sync\Iterator\StackableHelper;
use Schoolbox\Sync\Iterator\StackableTrait;
use Traversable;


class StudentIterator extends IteratorIterator
{
    use StackableHelper;
    use StackableTrait;

    /**
     * Ensure that $iterator is an actually an iterator, and not just a Traversable.
     *
     * @param Traversable $iterator
     */
    public function __construct(Traversable $iterator)
    {
        parent::__construct(new IteratorIterator($iterator));
    }


    /**
     * @return string
     */
    protected function getIdField(): string
    {
        return 'SchoolCode';
    }

    /**
     * @param  array $preStack
     * @return User\Student
     */
    public function buildFromStack(array $preStack): User\Student
    {
        $currentResult = current($preStack);
        $student       = new User\Student();

        $student
            ->setId((string)$currentResult->SchoolCode);

//        $student
//            ->setId($currentResult['student_id'])
//            ->setTitle($currentResult['student_title'])
//            ->setGivenName($currentResult['student_given_name'])
//            ->setPreferredName($currentResult['student_preferred'])
//            ->setLastname($currentResult['student_surname'])
//            ->setGender($currentResult['student_gender'])
//        ;
//
//        $student
//            ->getYear()
//            ->setLevel($currentResult['student_year_level'])
//        ;
//
//        $student
//            ->getHouse()
//            ->setCode($currentResult['student_house'])
//        ;
//
//        $student
//            ->getAccount()
//            ->setLogin($currentResult['network_login'])
//        ;
//
//        /* Emails - one for every item in stack
//         */
//
//        array_map([$student, "addEmail"], $this->columnValuesAsEmail($preStack, 'student_default_email'));
//        array_map([$student, "addWorkEmail"], $this->columnValuesAsEmail($preStack, 'student_work_email'));
//        array_map([$student, "addHomeEmail"], $this->columnValuesAsEmail($preStack, 'student_home_email'));
//
//        /* Boarding House should be set if it has a value
//         * You can't actually rely on StudentBoarder field, because that
//         * is not strictly yes/no; sometimes it contains B, W, D, etc.
//         * It's really a Student Boarder Type, more than anything
//         */
//        if (strlen($currentResult['student_boarding_house'])) {
//            $boardingHouse = new User\Common\BoardingHouse();
//            $boardingHouse
//                ->setCode($currentResult['student_boarding_house'])
//            ;
//
//            $student
//                ->setBoardingHouse($boardingHouse)
//            ;
//        }
//
//        /* Campus
//         */
//        $campus = new User\Common\Campus();
//        $campus
//            ->setCode($currentResult['student_campus'])
//        ;
//
//        $student
//            ->getCampus()
//            ->add($campus)
//        ;
        return $student;
    }
}
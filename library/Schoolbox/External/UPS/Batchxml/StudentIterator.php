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
            ->setId((string)$currentResult->SchoolCode)
            ->setTitle((string)$currentResult->Title)
            ->setGivenName((string)$currentResult->Forename)
            ->setPreferredName((string)$currentResult->Preferredname)
            ->setLastname((string)$currentResult->Surname)
            ->setGender((string)$currentResult->Gender);

        $student
            ->getYear()
                ->setDescription((string)$currentResult->Form)
                ->setLevel((string)$currentResult->NCYear);

        $student
            ->getHouse()
                ->setCode((string)$currentResult->AcademicHouse);

        $student
            ->getAccount()
                ->setLogin((string)$currentResult->UserName);

        $student
            ->addEmail((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

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
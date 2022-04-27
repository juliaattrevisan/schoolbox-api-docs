<?php

/**
 * Iterator over a `External_UserInterface->getStudentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Student` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;
use Traversable;

class Student extends IteratorIterator
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
    protected function getIdField()
    {
        return 'student_id';
    }

    /**
     * @param array $preStack
     * @return User\Student
     */
    public function buildFromStack(array $preStack)
    {
        $currentResult = current($preStack);
        $student       = new User\Student();
        $student
            ->setId($currentResult['student_id'])
            ->setTitle($currentResult['student_title'] ?? '')
            ->setGivenName($currentResult['student_given_name'])
            ->setPreferredName($currentResult['student_preferred'] ?? '')
            ->setLastname($currentResult['student_surname'])
            ->setGender($currentResult['student_gender'] ?? null);

        $student->getYear()->setLevel($currentResult['student_year_level'])->setDescription($currentResult['student_year_level_description'] ?? null);
        $student->getForm()->setDescription($currentResult['student_form_description'] ?? '');

        $student->getHouse()->setCode($currentResult['student_house']);

        $student->getAccount()->setLogin($currentResult['network_login']);

        /* Emails - one for every item in stack
         */
        array_map([$student, "addEmail"], $this->columnValuesAsEmail($preStack, 'student_default_email'));
        array_map([$student, "addWorkEmail"], $this->columnValuesAsEmail($preStack, 'student_work_email'));
        array_map([$student, "addHomeEmail"], $this->columnValuesAsEmail($preStack, 'student_home_email'));

        /* Set the student boarding status, effectively it's a bool
         */
        $student->setBoardingStatus((int)($currentResult['student_boarder'] ?? null));

        /* Boarding House should be set if it has a value
         * You can't actually rely on StudentBoarder field, because that
         * is not strictly yes/no; sometimes it contains B, W, D, etc.
         * It's really a Student Boarder Type, more than anything
         */
        if (strlen($currentResult['student_boarding_house'])) {
            $boardingHouse = new User\Common\BoardingHouse();
            $boardingHouse->setCode($currentResult['student_boarding_house']);

            $student->setBoardingHouse($boardingHouse);
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus->setCode($currentResult['student_campus']);

        $student->getCampus()->add($campus);

        $student->setCustomField1((string)($currentResult['custom_field1'] ?? null));
        $student->setCustomField2((string)($currentResult['custom_field2'] ?? null));
        $student->setCustomField3((string)($currentResult['custom_field3'] ?? null));
        $student->setCustomField4((string)($currentResult['custom_field4'] ?? null));
        $student->setCustomField5((string)($currentResult['custom_field5'] ?? null));

        return $student;
    }
}

<?php

/**
 * Iterator over a `External_UserInterface->getParentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Guardian` object.
 *
 * Note: most implementations of `External_UserInterface->getParentUserAccountInfo()`
 * return a parent and their spouse in a single row. This class expects that
 * parents and speese will be in separate rows.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;
use Traversable;

class Guardian extends IteratorIterator
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
        return 'student_contact_id';
    }

    /**
     * @param  array $preStack
     * @return User\Guardian
     */
    protected function buildFromStack(array $preStack)
    {

        // Forge a single parent out of the disparate parts of the prestack
        // Only the following fields will vary:
        // * student_year_level
        // * student_campus
        // * student_gender
        $currentResult = current($preStack);
        $guardian      = new User\Guardian();
        $guardian
            ->setId($currentResult['student_contact_id'])
            ->setTitle($currentResult['student_contact_title'] ?? '')
            ->setGivenName($currentResult['student_contact_given_name'] ?? '')
            ->setPreferredName($currentResult['student_contact_preferred'] ?? '')
            ->setLastname($currentResult['student_contact_surname'] ?? '')
        ;

        if (array_key_exists('student_contact_type', $currentResult)) {
            $guardian->setType($currentResult['student_contact_type']);
        }

        /* Email - one for every item in stack
         */

        array_map([$guardian, "addEmail"], $this->columnValuesAsEmail($preStack, 'student_contact_default_email'));
        array_map([$guardian, "addWorkEmail"], $this->columnValuesAsEmail($preStack, 'student_contact_work_email'));
        array_map([$guardian, "addHomeEmail"], $this->columnValuesAsEmail($preStack, 'student_contact_home_email'));

        /* Account
         */
        $guardian
            ->getAccount()
            ->setLogin($currentResult['student_contact_network_login'] ?? '')
        ;

        // Add details for each one of their students
        foreach ($preStack as $row) {
            // Students
            $student = new User\Student();
            $student
                ->setId($row['student_id'] ?? '')
                ->setPreferredName($row['student_preferred'] ?? '')
                ->setLastname($row['student_surname'] ?? '')
                ->setGender($row['student_gender'] ?? '')
            ;

            $student->getYear()->setLevel($row['student_year_level'] ?? '');
            $student->getForm()->setDescription($row['student_form_description'] ?? '');

            /* Campus
             */
            $campus = new User\Common\Campus();
            $campus->setCode($row['student_campus'] ?? '');
            $student->getCampus()->add($campus);

            /* House
             */
            $student->getHouse()->setCode($row['student_house'] ?? '');

            $guardian->addStudent($student);

            $guardian->setCustomField1((string)($currentResult['custom_field1'] ?? null));
            $guardian->setCustomField2((string)($currentResult['custom_field2'] ?? null));
            $guardian->setCustomField3((string)($currentResult['custom_field3'] ?? null));
            $guardian->setCustomField4((string)($currentResult['custom_field4'] ?? null));
            $guardian->setCustomField5((string)($currentResult['custom_field5'] ?? null));
        }
        return $guardian;
    }
}

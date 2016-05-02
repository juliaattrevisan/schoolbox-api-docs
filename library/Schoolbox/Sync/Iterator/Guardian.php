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
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class Guardian extends IteratorIterator
{
    use StackableTrait;

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
    protected function buildFromStack($preStack)
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
            ->setTitle($currentResult['student_contact_title'])
            ->setGivenName($currentResult['student_contact_given_name'])
            ->setPreferredName($currentResult['student_contact_preferred'])
            ->setLastname($currentResult['student_contact_surname'])
            ->setGender($currentResult['student_gender'])
        ;

        /* Email
         */
        $email = preg_split('/[,;]/', $currentResult['student_contact_default_email']);
        foreach ($email as $key => $anEmail) {
            $currentEmail = new User\Common\Email();
            $currentEmail->setAddress($anEmail);

            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $guardian->addEmail($currentEmail);
            }
        }

        /* Account
         */
        $guardian
            ->getAccount()
            ->setLogin($currentResult['student_contact_network_login'])
        ;

        // Add details for each one of their students
        foreach ($preStack as $row) {
            // Students
            $student = new User\Student();
            $student
                ->setId($row['student_id'])
                ->setPreferredName($row['student_preferred'])
                ->setLastname($row['student_surname'])
                ->setGender($row['student_gender'])
            ;

            $student
                ->getYear()
                ->setLevel($row['student_year_level'])
            ;

            /* Campus
             */
            $campus = new User\Common\Campus();
            $campus->setCode($row['student_campus']);
            $student->getCampus()->add($campus);
            $guardian->addStudent($student);
        }
        return $guardian;
    }
}

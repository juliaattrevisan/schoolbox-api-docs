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
use Traversable;

class Staff extends IteratorIterator
{
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
        return 'staff_id';
    }

    /**
     * @param  array $preStack
     * @return User\Staff
     */
    public function buildFromStack($preStack)
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getStaffUserAccountInfo)
        $currentResult = current($preStack);
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
            ->getYear()
            ->setLevel($currentResult['staff_year_level'])
            ->setDescription($currentResult['staff_year_level_description']);

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

        /* Email - one for every item in stack
         */
        $email = [];
        foreach ($preStack as $result) {
            $email = array_merge(
                $email,
                preg_split('/[,;]/', $result['email'])
            );
        }
        foreach (array_unique($email) as $key => $anEmail) {
            $currentEmail = new User\Common\Email();
            $currentEmail->setAddress($anEmail);

            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $staff->addEmail($currentEmail);
            }
        }

        /* Work Email - one for every item in stack
         */
        $workEmail = [];
        foreach ($preStack as $result) {
            $workEmail = array_merge(
                $workEmail,
                preg_split('/[,;]/', $result['work_email'])
            );
        }
        foreach (array_unique($workEmail) as $key => $anEmail) {
            /** @var User\Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $staff->addWorkEmail($currentEmail);
            }
        }

        /* Position - one for every item in stack
         */
        $position = [];
        foreach ($preStack as $result) {
            $position = array_merge(
                $position,
                preg_split('/[,;]/', $result['staff_position'])
            );
        }
        $staff->getMeta()->setPosition($position);
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

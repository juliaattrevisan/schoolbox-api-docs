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

        $staff
            ->getHouse()
            ->setCode($currentResult['staff_house']);

        /* Email - one for every item in stack
         */

        array_map([$staff, "addEmail"], $this->columnValuesAsEmail($preStack, 'default_email'));
        array_map([$staff, "addWorkEmail"], $this->columnValuesAsEmail($preStack, 'work_email'));
        array_map([$staff, "addHomeEmail"], $this->columnValuesAsEmail($preStack, 'home_email'));

        /* Position - one for every item in stack
         */

        $staff->getMeta()->setPosition($this->columnValuesAsArray($preStack, 'staff_position'));

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

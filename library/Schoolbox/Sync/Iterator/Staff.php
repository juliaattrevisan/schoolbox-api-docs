<?php

/**
 * Iterator over a `External_UserInterface->getStaffUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Staff` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\Collection;
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
     * @param array $preStack
     * @return User\Staff
     */
    public function buildFromStack(array $preStack)
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getStaffUserAccountInfo)
        $currentResult = current($preStack);
        $staff         = new User\Staff();
        $staff
            ->setId($currentResult['staff_id'])
            ->setTitle($currentResult['staff_title'])
            ->setPreferredName($currentResult['staff_preferred_name'] ?? '')
            ->setGivenName($currentResult['staff_given_name'] ?? '')
            ->setLastname($currentResult['staff_surname'])
            ->getMeta()
            ->setDepartment($currentResult['staff_department'] ?? null);

        if (isset($currentResult['staff_year_level'])) {
            $staff->getYear()->setLevel($currentResult['staff_year_level'])->setDescription($currentResult['staff_year_level_description'] ?? '');
        }

        $staff->getMeta()->getCategory()->setCode($currentResult['staff_category'])->setType($currentResult['staff_category_type']);

        /* Account
         */
        $staff->getAccount()->setLogin($currentResult['network_login'])->setActive($currentResult['active_flag'] ?? false);

        $staff->getHouse()->setCode($currentResult['staff_house']);

        /* Email - one for every item in stack
         */
        array_map([$staff, "addEmail"], $this->columnValuesAsEmail($preStack, 'default_email'));
        array_map([$staff, "addWorkEmail"], $this->columnValuesAsEmail($preStack, 'work_email'));
        array_map([$staff, "addHomeEmail"], $this->columnValuesAsEmail($preStack, 'home_email'));

        /* Collate every position into a csv string
         */
        $positionCodes = $this->columnValuesAsArray($preStack, 'staff_position_code');
        $positionDescriptions = $this->columnValuesAsArray($preStack, 'staff_position');

        /* Position - csv strings for multiple positions
         */
        $staff->getMeta()->getPosition()
            ->setCode(new Collection($positionCodes))
            ->setDescription(new Collection($positionDescriptions));

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus->setCode($currentResult['staff_campus']);

        $staff->getCampus()->add($campus);

        $staff->setCustomField1((string)($currentResult['custom_field1'] ?? null));
        $staff->setCustomField2((string)($currentResult['custom_field2'] ?? null));
        $staff->setCustomField3((string)($currentResult['custom_field3'] ?? null));
        $staff->setCustomField4((string)($currentResult['custom_field4'] ?? null));
        $staff->setCustomField5((string)($currentResult['custom_field5'] ?? null));

        return $staff;
    }
}

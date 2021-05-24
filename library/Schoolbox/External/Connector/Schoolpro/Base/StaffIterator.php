<?php

/**
 * StudentIterator
 *
 * Custom implementation for chub
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use Schoolbox\External\Normalise;
use Schoolbox\Sync\Iterator\Staff;

class StaffIterator extends Staff
{

    /**
     * @return string
     */
    protected function getIdField()
    {
        return 'ID';
    }


    /**
     * @param  array[] $preStack
     * @var    int     $preStack[]['ID']            eg 123
     * @var    string  $preStack[]['UserName']      eg "moxman"
     * @var    string  $preStack[]['Title']         eg "Mrs"
     * @var    string  $preStack[]['FirstName']     eg "Melanie"
     * @var    string  $preStack[]['Surname']       eg "Oxman"
     * @var    string  $preStack[]['StaffCategory'] eg "Teacher"
     * @var    string  $preStack[]['email']         eg "moxman@cedars.nsw.edu.au"
     * @var    int     $preStack[]['CampusID']      eg 1
     * @return Normalise\User\Staff
     */
    public function buildFromStack(array $preStack)
    {
        // Build a single object out of 1 or more rows.
        $currentResult = current($preStack);
        $staff = new Normalise\User\Staff();
        $staff
            ->setId($currentResult['ID'])
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['FirstName'])
            ->setLastname($currentResult['Surname'])
        ;

        $staff
            ->getMeta()
            ->getCategory()
            ->setType($currentResult['StaffCategory'])
        ;

        /* Account
         */
        $staff
            ->getAccount()
            ->setLogin($currentResult['UserName'])
            ->setActive(1)
        ;

        // Email - one for every item in stack
        array_map([$staff, 'addEmail'], $this->columnValuesAsEmail($preStack, 'email'));

        /* Campus
         */
        $campus = new Normalise\User\Common\Campus();
        $campus
            ->setCode($currentResult['CampusID'])
        ;

        $staff
            ->getCampus()
            ->add($campus)
        ;

        return $staff;
    }
}

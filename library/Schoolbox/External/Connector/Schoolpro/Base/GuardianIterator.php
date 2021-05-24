<?php

/**
 * Custom implementation for chub
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use Schoolbox\External\Normalise;
use Schoolbox\Sync\Iterator\Guardian;

class GuardianIterator extends Guardian
{

    /**
     * @inheritdoc
     */
    protected function getIdField()
    {
        return 'ID';
    }


    /**
     * @param  array[] $preStack
     * @var    int     $preStack[]['ID']            eg 80
     * @var    string  $preStack[]['UserName']      eg "kblackmore80"
     * @var    string  $preStack[]['Title']         eg "Mrs"
     * @var    string  $preStack[]['FirstName']     eg "Keren"
     * @var    string  $preStack[]['Surname']       eg "Blackmore"
     * @var    string  $preStack[]['email']         eg "kezzbla@gmail.com"
     * @var    int     $preStack[]['CampusID']      eg 1
     * @return Normalise\User\Guardian
     */
    public function buildFromStack(array $preStack)
    {
        // Build a single object out of 1 or more rows.
        $currentResult = current($preStack);

        $guardian       = new Normalise\User\Guardian();
        $guardian
            ->setId($currentResult['ID'])
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['FirstName'])
            ->setLastname($currentResult['Surname'])
        ;

        /* Account
         */
        $guardian
            ->getAccount()
            ->setLogin($currentResult['UserName'])
        ;

        // Email - one for every item in stack
        array_map([$guardian, 'addEmail'], $this->columnValuesAsEmail($preStack, 'email'));

        /* Find assorted children
         *
         * For some reason I can't run a stored procedure while
         * I am currently iterating the resultset of a previous
         * stored procedure, like this scenario
         *
         * Calls to getEnrolledStudents() returns false while iterating
         * the resultset of another stored proc
         *
         * Orvad 6067 has one child
         *
         * This forces you to have Skip Parents With No Enrolled Children
         * custom hydrator to be off
         *
         * It also forces you to have 'ADD PARENT TO ALL CHILDREN'S CAMPUSES' to be off.
          * // TODO - should probably disable these custom rules in GUI, or find work-around
         *
        $userHandler = new \External_User();
        $children    = $userHandler->getParent((int)$currentResult['ID']);
         */

        return $guardian;
    }
}

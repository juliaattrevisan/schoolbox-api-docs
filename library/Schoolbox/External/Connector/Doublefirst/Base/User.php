<?php
/**
 * Doublefirst implementation of user connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Doublefirst\Base;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IUser;

class User extends Base implements IUser
{
    /**
     * @inheritdoc
     */
    function getPortrait($externalId)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    function getGuardians($externalIds, $hydrateStudents = false)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getGuardianDirectory($filter = [])
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getStudents($externalIds, $hydrateGuardians = false)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getStudentsAccessible($externalID)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getStaff($externalId)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getStaffDirectory($filter = [])
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getTutors($externalIds)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    function getEmails($externalIds)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    function getCampusInvolvement($externalId)
    {
        return new Collection();
    }
}
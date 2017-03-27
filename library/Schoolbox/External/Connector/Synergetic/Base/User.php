<?php
/**
 * Synergetic implementation of user connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\UserInterface;

class User extends Base implements UserInterface
{
    /**
     * @inheritdoc
     */
    function getPortrait($externalId)
    {

    }

    /**
     * @inheritdoc
     */
    function getGuardians($externalIds, $hydrateStudents = false)
    {

    }

    /**
     * @inheritdoc
     */
    function getGuardianDirectory($filter = [])
    {

    }

    /**
     * @inheritdoc
     */
    function getStudents($externalIds, $hydrateGuardians = false)
    {

    }

    /**
     * @inheritdoc
     */
    function getStudentsAccessible($externalID)
    {

    }

    /**
     * @inheritdoc
     */
    function getStaff($externalId)
    {

    }

    /**
     * @inheritdoc
     */
    function getStaffDirectory($filter = [])
    {

    }

    /**
     * @inheritdoc
     */
    function getTutors($externalIds)
    {

    }

    /**
     * @inheritdoc
     */
    function getEmails($externalIds)
    {

    }

    /**
     * @inheritdoc
     */
    function getCampusInvolvement($externalId)
    {

    }
}
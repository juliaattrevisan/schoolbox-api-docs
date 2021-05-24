<?php

/**
 * Tass implementation of user connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IUser;
use Schoolbox\Resource\Storage;
use Schoolbox\User as SchoolboxUser;

class User extends Base implements IUser
{
    /**
     * @inheritdoc
     */
    public function getPortrait(SchoolboxUser $user): ?Storage\Model
    {
        return null;
    }


    /**
     * @inheritdoc
     */
    public function getGuardians($externalIds, $hydrateStudents = false)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getGuardianDirectory(array $filter = [])
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getStudents($externalIds, $hydrateGuardians = false)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getStudentsAccessible($externalID)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getStaff($externalId)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getStaffDirectory(array $filter = [])
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getTutors($externalIds)
    {
        return new Collection();
    }

    /**
     * @inheritdoc
     */
    public function getEmails($externalIds): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getCampusInvolvement($externalId)
    {
        return new Collection();
    }
}

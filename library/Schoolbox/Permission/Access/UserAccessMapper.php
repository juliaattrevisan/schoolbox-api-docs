<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class UserAccessMapper extends Mapper
{

    /**
    * @return void
    */
    protected function mapping()
    {
        $this->hasTable('userAccess');
        $this->hasModel('\\Schoolbox\\Permission\\Access\\UserAccess');

        $this->hasMap(array(
            'column'     => 'uid',
            'property'   => 'user',
            'type'       => self::TYPE_MODEL,
            'identifier' => true,
            'join'       => 'user'
        ));

        $this->hasMap(array(
            'column'     => 'service',
            'property'   => 'service',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'sid',
            'property'   => 'serviceIdentifier',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'type',
            'property'   => 'accessLevel',
            'type'       => self::TYPE_INTEGER
        ));

        $this->hasJoin('user', array(
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'uid',
            'other_key' => 'id'
        ));

    }

    /**
     * Determine whether $folder has a $user of type 3
     *
     * @param CategoryModel $folder
     * @return bool
     */
    public function hasFolderOwner(CategoryModel $folder)
    {
        $userAccess = $this->findByColumnList(array(
            'sid' => $folder->getId(),
            'service' => HOMEPAGE_SERVICE,
            'type' => UserAccess::ACCESS_FULL
        ));

        if ($userAccess->count()) {
            return true;
        }

        return false;
    }
    /**
     * Determine whether $user owns $folder
     *
     * note: superusers aren't necessarily owners of all folders.
     *
     * @param User $user
     * @param CategoryModel $folder
     * @return bool
     */
    public function isFolderOwner(User $user, CategoryModel $folder)
    {
        $userAccess = $this->findByColumnList(array(
            'sid' => $folder->getId(),
            'service' => HOMEPAGE_SERVICE,
            'type' => UserAccess::ACCESS_FULL,
            'uid' => $user->getId()
        ));

        if ($userAccess->count()) {
            return true;
        }

        return false;
    }

    /**
     * Returns all the UserAccess associated with the given user
     *
     * @param User $user
     * @param bool $includeSynergy
     * @return Collection|UserAccess[]
     */
    public function findAllForUser(User $user, $includeSynergy = true)
    {
        $access = new Collection();

        // start with the userAccess table (filtering away access level 0)
        $internal = $this->findByColumnList(['uid' => $user->getId(), 'type' => [ UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL ] ])->map(function ($ua) use ($user) {
            /** @var UserAccess $ua */
            // don't needlessly hold stub replicas of the user in memory
            return $ua->setUser($user);
        });

        $access->mergeUnique($internal);

        // we can bug out early if no external sources need to be considered
        if (!$includeSynergy) {
            return $access;
        }

        // TODO get rid of this
        $_DEPRECATED_EXTERNAL_USER_HANDLER = new \External_User();

        $extSubjs = $_DEPRECATED_EXTERNAL_USER_HANDLER->getSubjectList($user->getExternalId(), $user->getId());

        $external = new Collection();

        // in case this is false, stop here
        if (!$extSubjs) {
            return $access;
        }

        foreach ($extSubjs as $extSubj) {
            try {
                $catId = CategoryMapper::getInstance()->findByCode($extSubj['code'])->getId();

                $ua = new UserAccess();
                $ua->setService(UserAccess::SERVICE_HOMEPAGE)
                   ->setServiceIdentifier($catId)
                   ->setAccessLevel(UserAccess::ACCESS_READ)
                   ->setUser($user);

                $external->add($ua);

            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop
            }
        }

        $access->mergeUnique($external);

        return $access;
    }


    /**
    * @param CategoryModel $category
    * @param bool $includeSynergy
    * @return Collection|User[]
    */
    public function findStaffInCategory($category, $includeSynergy = true)
    {
        return $this->findUsersInCategory($category, Role::STAFF, UserAccess::ACCESS_READ, $includeSynergy);
    }


    /**
    * @param CategoryModel $category
    * @param bool $includeSynergy
    * @return Collection|User[]
    */
    public function findStudentsInCategory($category, $includeSynergy = true)
    {
        return $this->findUsersInCategory($category, Role::STUDENT, UserAccess::ACCESS_READ, $includeSynergy);
    }

    /**
     * Returns a list of users who are members of a group
     *
     * @param CategoryModel $category
     * @return Collection|User[]
     */
    public function findAdminsInCategory($category)
    {
        //TODO role type should be optional
        return $this->findUsersInCategory($category, Role::STAFF, $accessType = UserAccess::ACCESS_FULL, false);
    }


    /**
    * @param CategoryModel $category
    * @param string $roleType
     * @param int $accessType
    * @param bool $includeSynergy
    * @return Collection|User[]
    */
    public function findUsersInCategory($category, $roleType, $accessType = UserAccess::ACCESS_READ, $includeSynergy = true)
    {
        // union 3 sources: userAccess, and if synergy, external class and external user

        // acquire users from internal access definition
        $stmt = $this->database->prepare(
            "
			SELECT
				{$this->getJoinColumnString('user', 'u')}
			FROM
				`{$this->table}` ua
				LEFT JOIN {$this->getJoinSql('user', 'ua', 'u')}
				LEFT JOIN groups g on u.group_id = g.id
			WHERE ua.service = :service
			AND ua.sid = :serviceId
			AND ua.type >= :minAccess
			AND g.group_type = :roleType
		"
        );

        $stmt->execute(array(
            'service'   => UserAccess::SERVICE_HOMEPAGE,
            'serviceId' => $category->getId(),
            'minAccess' => $accessType,
            'roleType'  => $roleType
        ));
        $userCollection = $this->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // add the user represented by this row to the collection
            $userCollection->add(
                $this->hydrateJoin($r, 'user', 'u')
            );
        }

        if (!$includeSynergy) {
            // no synergy? ok, we're done here
            return $userCollection;
        }

        // TODO get rid of these
        $_DEPRECATED_EXTERNAL_CLASS_HANDLER = new \External_Class();

        // including synergy: intersect the existing collection with synergy-based collections

        // start with a list of students in this category
        switch($roleType) {
            case Role::STUDENT:
                $classListArray = $_DEPRECATED_EXTERNAL_CLASS_HANDLER->getStudents($category->getCodeString()) ?: array();
                break;
            case Role::STAFF:
                $classListArray = $_DEPRECATED_EXTERNAL_CLASS_HANDLER->getTeacherName($category->getCodeString()) ?: array();
                break;
            default:
                $classListArray = array();
        }

        $synergyIdArray = array();

        // index by synid
        foreach ($classListArray as $array) {
            $synergyIdArray[$array['synergy_id']] = $array['synergy_id'];
        }

        $extUserCollection = UserMapper::getInstance()->findByColumnList(array(
            'synergy_id' => array_values($synergyIdArray),
            'group_id'   => RoleMapper::getInstance()->findByColumn('group_type', $roleType)->getColumnValues('id')
        ));

        // merge the two collections
        $extUserCollection->mergeUnique($userCollection, 'id');
        return $extUserCollection;
	}

    /**
     * Gets users that belong to a given user access type.
     *
     * @param int $service    The service to check - one of the UserAccess::SERVICE_* constants
     * @param int $serviceId  The identifier of the service to check
     * @param int $accessType The minimum access level to retrieve users for.
     *                        Defaults to UserAccess::ACCESS_READ
     *
     * @return User[]|Collection
     */
    public function findUsersForService($service, $serviceId, $accessType = UserAccess::ACCESS_READ)
    {
        $stmt = $this->getDatabase()->prepare("
            SELECT
                {$this->getJoinColumnString('user', 'u')}
            FROM
                `{$this->getTable()}` ua
                LEFT JOIN {$this->getJoinSql('user', 'ua', 'u')}
                LEFT JOIN groups g on u.group_id = g.id
            WHERE ua.service = :service
            AND ua.sid = :serviceId
            AND ua.type >= :minAccess
            ORDER BY u.id DESC
        ");
        $stmt->execute(
            array(
                'service'   => $service,
                'serviceId' => $serviceId,
                'minAccess' => $accessType,
            )
        );
        $userCollection = $this->createCollection();
        foreach ($stmt as $row) {
            $userCollection->add(
                $this->hydrateJoin($row, 'user', 'u')
            );
        }
        return $userCollection;
    }
}

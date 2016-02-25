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
     * Cached permissions
     * Nested array structure $cachedPermissions[id][service][sid]
     */
    protected $cachedPermissions = [];

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
        $access = $this->findAllGroupsForUser($user);

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

            } catch (Mapper\Exception $e) {
                // noop
            }
        }

        $access->mergeUnique($external);

        return $access;
    }

    /**
     * Find all UserAccess instances for a User
     *
     * @param User $user
     * @return Collection
     */
    public function findAllGroupsForUser(User $user)
    {
        $access = new Collection();

        // start with the userAccess table (filtering away access level 0)
        $internal = $this->findByColumnList([
            'uid' => $user->getId(),
            'type' => [UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL]
        ])->map(function ($ua) use ($user) {
            /** @var UserAccess $ua */
            // don't needlessly hold stub replicas of the user in memory
            return $ua->setUser($user);
        });

        $access->mergeUnique($internal);

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


    /**
     * @param int   $userId
     * @param array $serviceIds
     * @param int   $minimumLevel
     * @param int   $service
     * @return bool
     */
    public function userHasMinimumAccessToAll($userId, $serviceIds, $minimumLevel, $service = UserAccess::SERVICE_HOMEPAGE)
    {
        return $this->userHasMinAccess(true, $userId, $serviceIds, $minimumLevel, $service);
    }


    /**
     * @param int   $userId
     * @param array $serviceIds
     * @param int   $minimumLevel
     * @param int   $service
     * @return bool
     */
    public function userHasMinimumAccessToAny($userId, $serviceIds, $minimumLevel, $service = UserAccess::SERVICE_HOMEPAGE)
    {
        return $this->userHasMinAccess(false, $userId, $serviceIds, $minimumLevel, $service);
    }


    /**
     * @param bool  $all
     * @param int   $userId
     * @param array $serviceIds
     * @param int   $minimumLevel
     * @param int   $service
     * @return bool
     */
    protected function userHasMinAccess($all, $userId, $serviceIds, $minimumLevel, $service)
    {
        // Could be written as... :P
        // return !!(count(array_filter($this->findByColumnList(['uid'=>$userId,'service'=>$service,'sid'=>$serviceIds])->getColumnValues('accessLevel'),function($v)use($minimumLevel){return$v>=$minimumLevel;}))==count($serviceIds));

        $filter = function ($v) use ($minimumLevel) {
            return $v >= $minimumLevel;
        };

        $search = [
            'uid'     => $userId,
            'service' => $service,
            'sid'     => (array)$serviceIds
        ];

        $levels = $this->findByColumnList($search)->getColumnValues('accessLevel');

        $preCount  = count($serviceIds);
        $postCount = count(array_filter($levels, $filter));

        if ($all) {
            // check permission is in all
            return $preCount == $postCount;
        }

        // check permission is in any
        return $postCount > 0;
    }

    /**
     * When passed a schoolbox user ID and subject code (eg. 'CH123') returns true/false to indicate the users
     * involvement in the class
     *
     * @param int       $userID
     * @param string    $code
     * @return bool
     */
    public function involvedInSubject($userID, $code)
    {
        try {
            $user = UserMapper::getInstance()->findCheaplyById($userID);
        } catch (\Exception $e) {
            return false;
        }

        if ($user->getExternalId()) {
            $externalUser = new \External_User;
            $externalSubjectList = $externalUser->getSubjectList($user->getExternalId(), $user->getId());

            if (is_array($externalSubjectList)) {
                foreach ($externalSubjectList as $currentSubject) {
                    if (strtolower($currentSubject["code"]) == strtolower($code)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param User $user
     * @param int  $serviceIdentifier
     * @param int  $service
     * @return UserAccess
     */
    public function getAccess(User $user, $serviceIdentifier, $service = UserAccess::SERVICE_HOMEPAGE)
    {

        // Order of priority for permissions
        //
        // 1. Previously cached results
        // 2. UserAccess table
        // 3. External DB access (via Synergetic class code)

        // If we are getting the permission for a homepage, we need to take into account inheritance

        if ($service == HOMEPAGE_SERVICE) {

            // Find the NNIAN for the folder

            $categoryNnian = CategoryMapper::getInstance()->findNearestNonInheritingAncestralNode(
                CategoryMapper::getInstance()->findCheaplyById($serviceIdentifier)
            );

            $serviceIdentifier = $categoryNnian->getId();
        }

        // Not sure what this is for (but Steve does apparently)

        if (!isset($serviceIdentifier)) {
            return $this->findOneByColumnList(
                array(
                    'service' => $service,
                    'uid'     => $user->getId(),
                )
            );
        }

        // Setup cache array for this user/service/sid if it doesn't already exist)

        if (!isset($this->cachedPermissions[$user->getId()])) {
            $this->cachedPermissions[$user->getId()] = [];
        }

        if (!isset($this->cachedPermissions[$user->getId()][$service])) {
            $this->cachedPermissions[$user->getId()][$service] = [];
        }

        // Permission check #1 - Cached results

        if (isset($this->cachedPermissions[$user->getId()][$service][$serviceIdentifier])) {
            // Found cached result, return it
            return $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier];
        }

        // Permission check #2 - UserAccess table (last chance before giving up)

        try {

            $result = $this->findOneByColumnList(
                array(
                    'service' => $service,
                    'sid' => $serviceIdentifier,
                    'uid' => $user->getId(),
                )
            );

            // Found a result in UserAccess, cache it and return the permission object

            $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = $result;
            return $result;

        } catch (\Exception $e) {

            // Exception thrown if not found. Noop
        }

        // Permission check #3 - External DB

        if ($user->getRole()->getType() == 'student') {
            $allUsers = $this->findStudentsInCategory(CategoryMapper::getInstance()->findById($serviceIdentifier), true);
        } else {
            $allUsers = $this->findStaffInCategory(CategoryMapper::getInstance()->findById($serviceIdentifier), true);
        }

        foreach ($allUsers as $userCurrent)
        {
            if ($userCurrent->getId() == $user->getId())
            {
                // Found matching student, give them read/write perms if student/staff respectively

                $model = new UserAccess();
                $model->setService($service);
                $model->setServiceIdentifier($serviceIdentifier);
                $model->setUser($user);
                $model->setAccessLevel(
                    $user->getRole()->getType() == 'student'
                        ? UserAccess::ACCESS_READ
                        : UserAccess::ACCESS_WRITE);

                // Cache for future calls and return the permission object

                $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = $model;
                return $model;
            }
        }

        // The users got no permission in either UserAccess or external DB, return a deny object

        $model = new UserAccess();
        $model->setService($service);
        $model->setServiceIdentifier($serviceIdentifier);
        $model->setUser($user);
        $model->setAccessLevel(UserAccess::ACCESS_DENY);

        // Cache for future calls and return the permission object

        $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = $model;
        return $model;
    }
}

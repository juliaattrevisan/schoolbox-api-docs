<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;

use Alaress\Redis;
use Monolog\Logger;
use PDO;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\Realtime\Routing\Subscription\Loader\Factory;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Code\Mapper as CodeMapper;

/**
 * Class UserAccessMapper
 *
 * @package Schoolbox\Permission\Access
 * @method UserAccess createModel($id = null)
 * @method UserAccess findOneByColumnList($columnList, $order = [], $options = [])
 * @method GenericCollection|UserAccess[] findByColumnList($columnList, $order = [])
 */
class UserAccessMapper extends Mapper
{
    /** @var WritableCache */
    private $cache;

    /**
     * Secondary hook method which occurs after successful construction
     *
     * This was made because a subclass of Mapper needed to make a
     * db call, but that needs to occur after mapping() since it
     * defines models and tables (unavailable in setup() atm)
     */
    protected function postSetup()
    {
        /** @var UserMapper $map */
        $map   = $this->getJoinMapper('user');
        $redis = Redis::getClientInstance();
        $gen   = new Generator();
        $log   = new Logger('');

        $factory = (new Factory($redis, $log))
            ->setGenerator($gen)
            ->setUserMapper($map);

        $this->cache = new WriteCache($redis, $log, $factory);
    }


    /**
     * Cached permissions
     * Nested array structure $cachedPermissions[id][service][sid]
     * @var array
     */
    protected $cachedPermissions = [];

    /**
    * @return void
    */
    protected function mapping()
    {
        $this->hasTable('userAccess');
        $this->hasModel(UserAccess::class);

        $this->joins('uid', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('service', self::TYPE_INTEGER);
        $this->uses('sid', self::TYPE_INTEGER, null, 'serviceIdentifier');
        $this->uses('type', self::TYPE_INTEGER, null, 'accessLevel');

        $this->registerCallback(self::CALLBACK_POSTSAVE,   [ $this, 'calculateUserSubscription' ]);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [ $this, 'calculateUserSubscription' ]);
    }


    /**
     * Removes any registered callbacks on this mapper
     *
     * @return $this
     */
    public function unregisterCallbacks(): self
    {
        $this->callback_queue[self::CALLBACK_POSTSAVE]   = [];
        $this->callback_queue[self::CALLBACK_POSTDELETE] = [];

        return $this;
    }

    /**
     * We don't actually care about *how* the user's access has changed (added, removed, etc), only that it has.
     * On that basis, the cache will recalculate
     *
     * @param UserAccess $model
     * @throws Mapper\DefinitionException
     */
    public function calculateUserSubscription(UserAccess $model)
    {
        if ($model->getService() !== UserAccess::SERVICE_HOMEPAGE) {
            // at present only group changes influence subscriptions, so ignore everything else
            return;
        }

        $this->cache->cacheUser(INSTANCE_ID, $model->getUser()->getId());
    }


    /**
     * Determine whether $folder has a $user of type 3
     *
     * @param FolderModel $folder
     * @return bool
     */
    public function hasFolderOwner(FolderModel $folder)
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
     * @param FolderModel $folder
     * @return bool
     */
    public function isFolderOwner(User $user, FolderModel $folder)
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
        $internal = $this->findByColumnList([
            'uid' => $user->getId(),
            'type' => [UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL],
        ])->map(function ($ua) use ($user) {
            /** @var UserAccess $ua */
            // don't needlessly hold stub replicas of the user in memory
            return $ua->setUser($user);
        });

        $access = $access->merge($internal);

        // we can bug out early if no external sources need to be considered
        if (!$includeSynergy) {
            return $access;
        }

        // TODO get rid of this
        $_DEPRECATED_EXTERNAL_USER_HANDLER = new \External_User();

        $extSubjs = $_DEPRECATED_EXTERNAL_USER_HANDLER->getSubjectList($user);

        $external = new Collection();

        // in case this is false, stop here
        if (!$extSubjs) {
            return $access;
        }

        foreach ($extSubjs as $extSubj) {
            try {
                $catId = FolderMapper::getInstance()->findByCode($extSubj['code'])->getId();

                $ua = new UserAccess();

                $ua->setService(UserAccess::SERVICE_HOMEPAGE)
                   ->setServiceIdentifier($catId)
                   ->setUser($user);

                //Teachers automatically get write access to their class pages
                if($user->getRole()->isStaff()) {
                    $ua->setAccessLevel(UserAccess::ACCESS_WRITE);
                }
                else {
                    $ua->setAccessLevel(UserAccess::ACCESS_READ);
                }

                $external->add($ua);

            } catch (Mapper\Exception $e) {
                // noop
            }
        }

        $access = $access->merge($external);
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
        return $this->findServiceForUser($user, UserAccess::SERVICE_HOMEPAGE);
    }


    /**
     * Find all UserAccess instances for a User of a given service
     *
     * @param User $user
     * @param int  $service
     * @return Collection
     */
    public function findServiceForUser(User $user, $service)
    {
        $access = new Collection();

        // start with the userAccess table (filtering away access level 0)
        $internal = $this->findByColumnList([
            'uid' => $user->getId(),
            'type' => [UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL],
            'service' => $service,
        ])->map(function ($ua) use ($user) {
            /** @var UserAccess $ua */
            // don't needlessly hold stub replicas of the user in memory
            return $ua->setUser($user);
        });

        $access = $access->merge($internal);

        return $access;
    }

    /**
    * @param FolderModel $folder
    * @param bool $includeSynergy
    * @return Collection|User[]
    */
    public function findStaffInCategory($folder, $includeSynergy = true)
    {
        return $this->findUsersInCategory($folder, Role::STAFF, UserAccess::ACCESS_READ, $includeSynergy);
    }

    /**
     * Returns a list of users who are most likely to be teachers in this folder
     *
     * @param FolderModel $folder
     * @return Collection|User[]
     */
    public function findTeachersInCategory($folder)
    {
        return $this->findUsersInCategory($folder, Role::STAFF, UserAccess::ACCESS_WRITE, true);
    }

    /**
     * @param FolderModel $folder          Folder codes must be hydrated if $includeSynergy == true
     * @param bool        $includeSynergy
     * @return Collection|User[]
    */
    public function findStudentsInCategory($folder, $includeSynergy = true)
    {
        return $this->findUsersInCategory($folder, Role::STUDENT, UserAccess::ACCESS_READ, $includeSynergy);
    }

    /**
     * Returns a list of users who are members of a group
     *
     * @param FolderModel $folder
     * @return Collection|User[]
     */
    public function findAdminsInCategory($folder)
    {
        //TODO role type should be optional
        return $this->findUsersInCategory($folder, Role::STAFF, UserAccess::ACCESS_FULL, false);
    }


    /**
     * @param FolderModel $folder      Folder codes must be hydrated if $includeSynergy == true
     * @param string      $roleType    If provided, restrict to this role type
     * @param int         $accessType
     * @param bool        $includeSynergy
     * @param bool        $exactAccess If true, only fetch users with exactly $accessType.
     *                                 By default, fetch users with at least $accessType.
     * @return Collection|User[]
     */
    public function findUsersInCategory(
        $folder,
        $roleType = null,
        $accessType = UserAccess::ACCESS_READ,
        $includeSynergy = true,
        $exactAccess = false
    ) {
        // union 3 sources: userAccess, and if synergy, external class and external user

	    $userRoleMapper = $this->getJoinMapper('user.role');

        // acquire users from internal access definition
        $query = "
            SELECT
                {$this->getJoinColumnString('user', 'u')},
                {$userRoleMapper->getColumnString('ur')}
            FROM
                `{$this->table}` ua
                INNER JOIN {$this->getJoinSql('user', 'ua', 'u')}
                INNER JOIN {$this->getJoinSql('user.role','u', 'ur', true)}
            WHERE ua.service = :service
            AND ua.sid = :serviceId
        ";
        if ($exactAccess) {
            $query .= ' AND ua.type = :access ';
        } else {
            $query .= ' AND ua.type >= :access ';
        }
        $params = [
            'service'   => UserAccess::SERVICE_HOMEPAGE,
            'serviceId' => $folder->getId(),
            'access'    => $accessType,
        ];
        if (isset($roleType)) {
            $query .= ' AND ur.group_type = :roleType ';
            $params['roleType'] = $roleType;
        }
        $stmt = $this->database->prepare($query);
        $stmt->execute($params);
        $userCollection = $this->getJoinMapper('user')->createCollection();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // add the user represented by this row to the collection
            $userCollection->add(
                $this->hydrateJoin($r, 'user', 'u')
	                    ->setRole($userRoleMapper->hydrate($r, 'ur'))
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
                $classListArray = $_DEPRECATED_EXTERNAL_CLASS_HANDLER->getStudents($folder->getCodeString()) ?: array();
                break;
            case Role::STAFF:
                $classListArray = $_DEPRECATED_EXTERNAL_CLASS_HANDLER->getTeacherName($folder->getCodeString()) ?: array();
                break;
            default:
                $classListArray = array();
        }

        $synergyIdArray = array();

        // index by synid
        foreach ($classListArray as $value) {
            // compensate for <expletive of choice> inconsistent handler return type
            if (is_array($value)) {
                $synergyIdArray[$value['synergy_id']] = $value['synergy_id'];
            } else {
                $synergyIdArray[$value] = $value;
            }
        }

        $extUserCollection = UserMapper::getInstance()->findByColumnList(array(
            'synergy_id' => array_values($synergyIdArray),
            'group_id'   => RoleMapper::getInstance()->findByColumn('group_type', $roleType)->getColumnValues('id')
        ));

        // merge the two collections
        $extUserCollection = $extUserCollection->mergeUnique($userCollection, 'id');
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
        $userCollection = $this->getJoinMapper('user')->createCollection();
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

        // Find the NNIAN for each folder

        foreach ($serviceIds as $key => $serviceIdentifier) {
            if ($serviceIdentifier != 0) {
                $folder =  FolderMapper::getInstance()->findCheaplyById($serviceIdentifier);
                $folderNnian = FolderMapper::getInstance()->findNearestNonInheritingAncestralNode($folder);
                $serviceIds[$key] = $folderNnian->getId();
            }
        }

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
     * @param User       $user
     * @param string    $code
     * @return bool
     */
    public function involvedInSubject(User $user, $code)
    {
        // Take into account class codes with commas

        $codes = explode(",", $code);

        if ($user->getExternalId()) {
            $externalUser = new \External_User;
            $externalSubjectList = $externalUser->getSubjectList($user);

            if (is_array($externalSubjectList)) {
                foreach ($externalSubjectList as $currentSubject) {

                    foreach ($codes as $code) {

                        // Check if there is a campus mangled in the class code

                        if (strpos($code, '/') !== false) {
                            $codeArray = explode('/', $code);
                            if (strtolower($currentSubject["code"]) == strtolower($codeArray[1]) &&
                                strtolower($currentSubject["campusCode"]) == strtolower($codeArray[0])
                            ) {
                                return true;
                            }
                        } else {
                            if (strtolower($currentSubject["code"]) == strtolower($code)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if user is invovlved in a group
     * @param User $user
     * @param int $serviceIdentifier
     * @return bool
     */
    public function involvedInGroup(User $user, $serviceIdentifier) {
        try {
            $this->findOneByColumnList(
                array(
                    'service' => UserAccess::SERVICE_HOMEPAGE,
                    'sid' => $serviceIdentifier,
                    'uid' => $user->getId(),
                )
            );

            return true;
        } catch (Mapper\Exception $e) {
            return false;
        }
    }


    /**
     * @param User     $user
     * @param null|int $serviceIdentifier
     * @param int      $service
     * @param bool     $cache
     * @return UserAccess
     * @throws Mapper\Exception
     */
    public function getAccess(User $user, $serviceIdentifier, $service = UserAccess::SERVICE_HOMEPAGE, $cache = true)
    {
        // Order of priority for permissions
        //
        // 1. Previously cached results
        // 2. UserAccess table
        // 3. External DB access (via Synergetic class code)

        // Not sure what this is for (but Steve does apparently)

        if (!isset($serviceIdentifier)) {
            return $this->findOneByColumnList(
                array(
                    'service' => $service,
                    'uid'     => $user->getId(),
                )
            );
        }

        // If we are getting the permission for a homepage, we need to take into account inheritance

        if ($serviceIdentifier != 0 && $service == HOMEPAGE_SERVICE) {

            // Find the NNIAN for the folder
            $folder =  FolderMapper::getInstance()->findCheaplyById($serviceIdentifier, ['deleted' => true]);
            $folderNnian = FolderMapper::getInstance()->findNearestNonInheritingAncestralNode($folder);
            $serviceIdentifier = $folderNnian->getId();
        }

        // Setup cache array for this user/service/sid if it doesn't already exist)

        if (!isset($this->cachedPermissions[$user->getId()])) {
            $this->cachedPermissions[$user->getId()] = [];
        }

        if (!isset($this->cachedPermissions[$user->getId()][$service])) {
            $this->cachedPermissions[$user->getId()][$service] = [];
        }

        // Permission check #1 - Cached results

        if (isset($this->cachedPermissions[$user->getId()][$service][$serviceIdentifier]) && $cache) {

            if ($this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] == -1) {
                // Found cached exception, throw it
                throw new Mapper\Exception("Instance of object not found");
            }

            // Found cached result, return it
            return $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier];
        }

        // Permission check #2 - UserAccess table

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
        $folderCodeList = CodeMapper::getInstance()->findForCategory($serviceIdentifier);
        if(count($folderCodeList) > 0) {
            $allCodes = [];
            foreach ($folderCodeList as $currentCode) {
                $allCodes[]= $currentCode->getCode();
            }
            $result = $this->involvedInSubject($user, implode(',',$allCodes));
            if ($result) {
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


        // The users got no permission in either UserAccess or external DB

        // Cache and throw an exception

        $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = -1;
        throw new Mapper\Exception("Instance of object not found");
    }
}

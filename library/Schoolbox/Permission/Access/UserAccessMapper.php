<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Permission\Access;

use Alaress\Database\Predicate\Builder;
use function count;
use External_Class;
use function implode;
use function in_array;
use PDO;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Code\Mapper as CodeMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Database\Predicate;
use PDOException;
use UnderflowException;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Class UserAccessMapper
 *
 * @package Schoolbox\Permission\Access
 * @method UserAccess createModel($id = null)
 * @method UserAccess findOneByColumnList($columnList, array $options = [])
 * @method GenericCollection|UserAccess[] findByColumnList($columnList, $order = [])
 */
class UserAccessMapper extends Mapper
{

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

        $cache = self::getDi()->get('subscriptionCache');

        if ($cache) {
            $cache->cacheUsers(INSTANCE_ID, [ $model->getUser()->getId() ]);
        }
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
            'service' => UserAccess::SERVICE_HOMEPAGE,
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
            'service' => UserAccess::SERVICE_HOMEPAGE,
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
     * @return GenericCollection|UserAccess[]
     */
    public function findAllForUser(User $user, $includeSynergy = true): GenericCollection
    {
        $access = $this->createCollection();

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

        $external = $this->createCollection();

        // in case this is false, stop here
        if (!$extSubjs) {
            return $access;
        }

        foreach ($extSubjs as $extSubj) {
            // If, for some reason, code is not set, skip it.
            if (!isset($extSubj['code']) || empty($extSubj['code'])) {
                continue;
            }
            $folder = FolderMapper::getInstance()->findForCampusAndClass($extSubj['campusCode'] ?? '', $extSubj['code']);
            if (!$folder) {
                continue;
            }

            $ua = new UserAccess();

            $ua->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setServiceIdentifier($folder->getId())
                ->setUser($user);

            //Teachers automatically get write access to their class pages
            if ($user->getRole()->isStaff()) {
                $ua->setAccessLevel(UserAccess::ACCESS_WRITE);
            } else {
                $ua->setAccessLevel(UserAccess::ACCESS_READ);
            }

            $external->add($ua);

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
     * @return Collection|UserAccess[]
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
     * Efficiently returns a set of users involved with a folder
     *
     * union 2 sources: userAccess, and if synergy, external class
     *
     * @param FolderModel $folder      Folder codes must be hydrated if $includeSynergy == true
     * @param null|string $roleType    If provided, restrict to this role type
     * @param int         $accessType
     * @param bool        $includeSynergy
     * @param bool        $exactAccess If true, only fetch users with exactly $accessType.
     *                                 By default, fetch users with at least $accessType.
     * @return GenericCollection|User[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws Predicate\InvalidColumnException
     * @throws UnderflowException
     * @throws InvalidArgumentException If an unrecognised role type or access level is specified
     * @todo This doesn't belong on this mapper, and is too complex for a single method. It belong in a testable helper class that depends on:
     *       * userAccessMapper
     *       * userMapper
     *       * external class interface
     */
    public function findUsersInCategory(
        FolderModel $folder,
        string $roleType = null,
        int $accessType = UserAccess::ACCESS_READ,
        bool $includeSynergy = true,
        bool $exactAccess = false
    ): GenericCollection {
        if ($roleType && !in_array($roleType, Role::getRoleTypes(), true)) {
            throw new InvalidArgumentException("Unrecognised role type: $roleType");
        }

        if (!in_array($accessType, UserAccess::getAccessLevels(), true)) {
            throw new InvalidArgumentException("Unrecognised access level type: $accessType");
        }

        /**
         * @var UserMapper $userMapper
         * @var RoleMapper $roleMapper
         */
        $userMapper     = $this->getJoinMapper('user');
        $roleMapper     = $this->getJoinMapper('user.role');
        $userCollection = $userMapper->createCollection();

        // Performance testing appears to indicate there is no material difference between:
        //   1. UNION of two SELECT statements that each use INNER JOINs, and
        //   2. A single SELECT using a LEFT JOIN to include users with matching external ids, but who have no tuples in userAccess
        // However the LEFT JOIN version is more amenable to an ORDER BY clause.

        // If we have no `externalId` consideration, we can optimise to use an INNER JOIN to `userAccess` instead.
        $userAccessJoinType = 'INNER';

        // Group members (always part of ultimate query)
        $groupPb = (new Predicate\Builder($this->getDatabase(), 'u'))
            ->equals('ua.service', UserAccess::SERVICE_HOMEPAGE)
            ->equals('ua.sid', $folder->getId());
        if ($exactAccess) {
            $groupPb->equals('ua.type', $accessType);
        } else {
            $groupPb->greaterThanOrEqualTo('ua.type', $accessType);
        }
        $outerPb = (new Predicate\Builder($this->getDatabase(), 'u'))->all($groupPb);

        if ($includeSynergy) {
            // Include students if no role filter, or filtering to students, and caller requested exact READ perm, or at least READ perm or below.
            $getClassStudents =
                (!$roleType || $roleType === Role::STUDENT) &&
                (($exactAccess && $accessType === UserAccess::ACCESS_READ) || (!$exactAccess && $accessType <= UserAccess::ACCESS_READ));

            // Include teachers if no role filter, or filtering to teachers, and caller requested exact WRITE perm, or at least WRITE perm or below.
            $getClassTeachers =
                (!$roleType || $roleType === Role::STAFF) &&
                (($exactAccess && $accessType === UserAccess::ACCESS_WRITE) || (!$exactAccess && $accessType <= UserAccess::ACCESS_WRITE));

            if ($getClassStudents || $getClassTeachers) {
                $externalClass = new External_Class();
                $externalIds   = [];
                if ($getClassStudents) {
                    foreach ($externalClass->getStudents($folder->getCodeString()) as $student) {
                        $externalIds[] = $student['synergy_id'];
                    }
                }
                if ($getClassTeachers) {
                    foreach ($externalClass->getTeachers($folder->getCodeString()) as $teacherExternalId) {
                        $externalIds[] = $teacherExternalId;
                    }
                }
                if (count($externalIds)) {
                    // We'll need to change this to a LEFT join to include class members who lack userAccess records.
                    $userAccessJoinType = 'LEFT';
                    $outerPb->in('u.synergy_id', $externalIds);
                    $outerPb = (new Predicate\Builder($this->getDatabase(), 'u'))->any($outerPb);
                }
            }
        }

        if ($roleType) {
            $outerPb->equals('g.group_type', $roleType);
        }
        $outerPb->isNull('u.deleted_at');

        $whereClause = implode(' AND ', $outerPb->getWhere());

        $query = "
            SELECT
                {$userMapper->getColumnString('u')},
                {$roleMapper->getColumnString('g')}
            FROM
                {$userMapper->getTable()} u
                INNER JOIN {$userMapper->getJoinSql('role','u', 'g', true)}
                {$userAccessJoinType} JOIN {$this->table} ua ON ua.uid = u.id
            WHERE {$whereClause}
            ORDER BY ua.type DESC, u.lastname, u.firstname
        ";

        $stmt = $this->database->prepare($query);
        $stmt->execute($outerPb->getParams());

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // add the user represented by this row to the collection
            $userId = $r['u.id'];
            if (!$userCollection->keyExists($userId)) {
                $userCollection[$r['u.id']] = $userMapper->hydrate($r, 'u')->setRole($roleMapper->hydrate($r, 'g'));
            }
        }
        return $userCollection;
    }


    /**
     * Gets users that belong to a given user access type.
     *
     * If you don't specify a $serviceId it won't check for that particular $serviceId and give you everybody
     * who is involved with $service.
     *
     * @param int $service    The service to check - one of the UserAccess::SERVICE_* constants
     * @param int $serviceId  The identifier of the service to check
     * @param int $accessType The minimum access level to retrieve users for.
     *                        Defaults to UserAccess::ACCESS_READ
     *
     * @return User[]|Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findUsersForService($service, $serviceId = null, $accessType = UserAccess::ACCESS_READ)
    {
        $userMapper = $this->getJoinMapper('user');
        $roleMapper = $userMapper->getJoinMapper('role');

        $pb = new Builder($userMapper->getDatabase(), 'u');
        $pb->equals('ua.service', $service);
        $pb->greaterThanOrEqualTo('ua.type', $accessType);

        /* Find $service folks in $serviceId, if specified
         */
        if ($serviceId !== null) {
            $pb->equals('ua.sid', $serviceId);
        }

        $where = implode("\nAND ", $pb->getWhere());

        $query = "
            SELECT
                {$this->getJoinColumnString('user', 'u')},
                {$roleMapper->getColumnString( 'r')}
            FROM
                `{$this->getTable()}` ua
            INNER JOIN {$this->getJoinSql('user', 'ua', 'u')}
            LEFT JOIN {$userMapper->getJoinSql('role', 'u', 'r')}
            WHERE {$where}
            ORDER BY u.lastname
        ";

        $stmt = $this->getDatabase()->prepare($query);
        $stmt->execute($pb->getParams());

        $userCollection = $this->getJoinMapper('user')->createCollection();
        foreach ($stmt as $row) {
            $role = $roleMapper->hydrate($row, 'r');
            $user = $this->hydrateJoin($row, 'user', 'u');
            $user->setRole($role);

            $userCollection->add($user);
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
                        //If the code has a '/' it might not contain a campus
                        //The class code itself may have a '/' in it. Check for exact match first
                        if (strtolower($currentSubject["code"]) == strtolower($code)) {
                            return true;
                        }

                        //Then check if there is a campus mangled in the class code
                        if (strpos($code, '/') !== false) {
                            $codeArray = explode('/', $code, 2);
                            if (strtolower($currentSubject["code"]) == strtolower($codeArray[1]) &&
                                strtolower($currentSubject["campusCode"]) == strtolower($codeArray[0])
                            ) {
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
    public function getAccess(User $user, $serviceIdentifier, $service = UserAccess::SERVICE_HOMEPAGE, $cache = true): UserAccess
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

        if ($serviceIdentifier != 0 && $service == UserAccess::SERVICE_HOMEPAGE) {

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
                throw new Mapper\Exception\NotFound();
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

                $studentReadAccess = $this->getConfigLoader()->get('allow_student_class_access') ? UserAccess::ACCESS_READ : UserAccess::ACCESS_DENY;

                $model = new UserAccess();
                $model->setService($service);
                $model->setServiceIdentifier($serviceIdentifier);
                $model->setUser($user);
                $model->setAccessLevel(
                    $user->getRole()->getType() == 'student'
                        ? $studentReadAccess
                        : UserAccess::ACCESS_WRITE);

                // Cache for future calls and return the permission object

                $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = $model;
                return $model;
            }
        }


        // The users got no permission in either UserAccess or external DB

        // Cache and throw an exception

        $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = -1;
        throw new Mapper\Exception\NotFound();
    }
}

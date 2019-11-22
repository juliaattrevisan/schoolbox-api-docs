<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Permission\Access;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Config;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use InvalidArgumentException;
use External_UserInterface;
use External_User;
use LogicException;
use UnexpectedValueException;
use function count;
use function explode;
use function implode;
use function in_array;
use function max;
use const PHP_SAPI;

/**
 * Responsible for the application of various permission (aka access level) business rules and concepts.
 *
 * Business rules should be implemented here, rather than in the models and mappers to ensure:
 * 1. Cohesive responsibility sets
 * 2. Consistent architecture
 * 3. Testability
 *
 * Note that the caches are disabled in CLI/Daemon contexts to ensure long-running processes
 * respect changes occurring in database
 */
class Access
{

    /**
     * Permission/access level constants
     */
    // TODO Move these somewhere more appropriate in the config sub-system.
    const VISIBILITY_EVERYONE     = 0;             // Accessible by everyone
    const VISIBILITY_STAFF        = 1;             // Accessible by staff only
    const VISIBILITY_STAFF_PARENT = 2;             // Accessible by staff & parents only

    /**
     * Cached user service permissions
     * Nested array structure $cachedUserPermissions[uid][service][sid]
     * @var int[][][]
     */
    private $cachedUserServicePermissions = [];

    /**
     * Cached role folder permissions
     *
     * Nested array structure $cachedRolePermissions[roleId][folderId]
     * @var int[][]
     */
    private $cachedRoleFolderPermissions = [];

    /**
     * Stores the singleton instance
     *
     * @var self
     */
    static private $instance;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Folder\Code\Mapper */
    private $folderCodeMapper;

    /**
     * If not injected, lazy load this to avoid creating a DB connection on instantiation of this class
     *
     * @var null|External_UserInterface
     */
    private $externalUser;

    /** @var IConfigLoader */
    private $config;

    /** @var Folder\Code\ISubjectMatcher */
    private $subjectMatcher;

    /**
     * Returns whether results may be returned from cache
     * Does not affect whether most recent results are stored in cache
     *
     * @var bool
     */
    private $enableCache;


    /**
     * Retrieves the singleton instance of this class.
     *
     * The dependencies *SHOULD* be injected, but not enforced yet to enable backwards compatibility
     *
     * @param UserAccessMapper|null            $userAccessMapper
     * @param RoleAccessMapper|null            $roleAccessMapper
     * @param Folder\Mapper|null               $folderMapper
     * @param Folder\Code\Mapper|null          $folderCodeMapper
     * @param External_UserInterface|null      $externalUser
     * @param IConfigLoader|null               $config
     * @param Folder\Code\ISubjectMatcher|null $subjectMatcher
     * @param bool|null                        $enableCache
     * @return static
     */
    public static function getInstance(
        UserAccessMapper $userAccessMapper = null,
        RoleAccessMapper $roleAccessMapper = null,
        Folder\Mapper $folderMapper = null,
        Folder\Code\Mapper $folderCodeMapper = null,
        External_UserInterface $externalUser = null,
        IConfigLoader $config = null,
        Folder\Code\ISubjectMatcher $subjectMatcher = null,
        bool $enableCache = null
    ): Access {
        if (!self::$instance) {
            $config         = $config ?? new Config\Loader();
            self::$instance = new static(
                $userAccessMapper ?? UserAccessMapper::getInstance(),
                $roleAccessMapper ?? RoleAccessMapper::getInstance(),
                $folderMapper ?? Folder\Mapper::getInstance(),
                $folderCodeMapper ?? Folder\Code\Mapper::getInstance(),
                $externalUser,
                $config,
                $subjectMatcher ?? (new Folder\Code\SubjectCodeMatchFactory())->build($config->get(Config\ConfigMapper::NAME_EXTERNAL_TYPE)),
                $enableCache ?? PHP_SAPI !== 'cli'
            );
        }
        return self::$instance;
    }


    /**
     * Private constructor to enforce singleton pattern
     *
     * @param UserAccessMapper            $userAccessMapper
     * @param RoleAccessMapper            $roleAccessMapper
     * @param Folder\Mapper               $folderMapper
     * @param Folder\Code\Mapper          $folderCodeMapper
     * @param null|External_UserInterface $externalUser
     * @param IConfigLoader               $config
     * @param Folder\Code\ISubjectMatcher $subjectMatcher
     * @param bool                        $enableCache
     */
    private function __construct(
        UserAccessMapper $userAccessMapper,
        RoleAccessMapper $roleAccessMapper,
        Folder\Mapper $folderMapper,
        Folder\Code\Mapper $folderCodeMapper,
        ?External_UserInterface $externalUser,
        IConfigLoader $config,
        Folder\Code\ISubjectMatcher $subjectMatcher,
        bool $enableCache
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->folderMapper     = $folderMapper;
        $this->folderCodeMapper = $folderCodeMapper;
        $this->externalUser     = $externalUser;
        $this->config           = $config;
        $this->subjectMatcher   = $subjectMatcher;
        $this->enableCache      = $enableCache;
    }


    /**
     * Is the specified user an instructor for the specified folder ?
     *
     * An instructor is any user with WRITE+ permission to a folder
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    public function isInstructor(User $user, Folder\Model $folder): bool
    {
        return $this->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_WRITE;
    }

    /**
     * Is the specified user a learner of the specified folder ?
     *
     * A learner is any user with READ permission to a folder
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    public function isLearner(User $user, Folder\Model $folder): bool
    {
        return $this->getFolderAccess($user, $folder->getId()) === UserAccess::ACCESS_READ;
    }


    /**
     * Returns the minimum access level across the collection of specified folders. Checks perms from:
     * 1. User.SU status
     * 2. Group membership via `userAccess` table
     * 3. CMS read/write/full
     * 4. Folder Role perms
     * 5. SIS enrollment perms
     *
     * @param User  $user
     * @param int[] $folderIds
     * @param bool  $cache
     * @return int
     * @throws InvalidArgumentException If folder array is empty
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    public function getMinFolderAccess(User $user, array $folderIds, $cache = true): int
    {
        if (0 === count($folderIds)) {
            throw new InvalidArgumentException("Cannot determine minimum value in empty set");
        }

        // We must iterate all, rather than from index 1+ (ie fencepost alg.) to robustly account for different indexing
        $minAccess = null;
        foreach ($folderIds as $folderId) {
            $accessLevel = $this->getFolderAccess($user, $folderId, $cache);

            // Short-circuit if any level is DENY. Cannot get less than this.
            if (UserAccess::ACCESS_DENY === $accessLevel) {
                return UserAccess::ACCESS_DENY;
            }

            // First iteration ?
            $minAccess = null === $minAccess ? $accessLevel : min($minAccess, $accessLevel);
        }

        return $minAccess;
    }


    /**
     * Returns the user's access level to the specified service, with optional service identifier.
     *
     * If checking the SERVICE_HOMEPAGE, system will check:
     *   1. User.SU status
     *   2. Group membership via `userAccess` table
     *   3. CMS read/write/full
     *   4. Folder Role perms
     *   5. SIS enrollment perms
     * Else not SERVICE_HOMEPAGE
     *   1. `userAccess` table only
     *
     * @param User $user
     * @param int  $service
     * @param int  $serviceIdentifier
     * @param bool $cache
     * @return int
     * @throws InvalidArgumentException If called with a service identifier for a service that does not use them, or vice versa
     * @throws Mapper\Exception         If cannot find an entity for the service identifier (eg, folder not found).
     *                                  Note that we intentionally don't return DENY here, rather, the caller needs to be
     *                                  informed that their call could not be processed for some reason.
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    public function getAccessLevel(User $user, int $service, int $serviceIdentifier = UserAccess::SID_NONE, bool $cache = true): int
    {
        // Check cache
        if ($cache && $this->enableCache && isset($this->cachedUserServicePermissions[$user->getId()][$service][$serviceIdentifier])) {
            return $this->cachedUserServicePermissions[$user->getId()][$service][$serviceIdentifier];
        }

        // Homepage service is a special case due to inheritance, otherwise just check `userAccess` table
        $accessLevel = $service === UserAccess::SERVICE_HOMEPAGE
            ? $this->getFolderAccess($user, $serviceIdentifier, $cache)
            : $this->getUserAccessLevel($user, $service, $serviceIdentifier, $cache);

        $this->cachedUserServicePermissions[$user->getId()][$service][$serviceIdentifier] = $accessLevel;
        return $accessLevel;
    }


    /**
     * Returns the access level a roleType is given when involved with a folder via SIS membership
     *
     * @param string $roleType
     * @return int
     */
    public function getSisClassRoleTypeAccess(string $roleType): int
    {
        if (Role::STAFF === $roleType) {
            return UserAccess::ACCESS_WRITE;
        }
        if (Role::STUDENT === $roleType) {
            return $this->config->get('allow_student_class_access') ? UserAccess::ACCESS_READ : UserAccess::ACCESS_DENY;
        }
        // Guests and Parents are given no access
        return UserAccess::ACCESS_DENY;
    }


    /**
     * When passed a schoolbox user ID and folder code (eg. 'X/CH123') returns true/false to indicate the users
     * involvement in the class
     *
     * @param User   $user
     * @param string $folderCodeString Comma-separated codes, eg 'abc123,xyz456'
     * @return bool
     * @throws InvalidArgumentException
     * @throws LogicException If unable to build either an online or offline user connector
     */
    public function involvedInSubject(User $user, string $folderCodeString): bool
    {
        if (!$user->getExternalId()) {
            return false;
        }
        $externalSubjectList = $this->getExternalUser()->getSubjectList($user);
        if (!count($externalSubjectList)) {
            return false;
        }

        $codes = explode(',', $folderCodeString);
        foreach ($externalSubjectList as $subject) {
            foreach ($codes as $code) {
                if ($this->subjectMatcher->isMatch($code, $subject['campusCode'], $subject['code'])) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Returns whether the user is involved with a group.
     *
     * An explicit DENY permission does not constitute involvement.
     *
     * @param User $user
     * @param int  $folderId
     * @return bool
     * @throws InvalidColumnException
     */
    public function involvedInGroup(User $user, int $folderId): bool
    {
        return $this->userAccessMapper->findByPredicates(
            $this->userAccessMapper->getPredicateBuilder()
                                   ->equals('service', UserAccess::SERVICE_HOMEPAGE)
                                   ->equals('sid', $folderId)
                                   ->equals('uid', $user->getId())
                                   ->notEquals('type', UserAccess::ACCESS_DENY)
        )->count() > 0;
    }


    /**
     * Return the specified user's access level for the given folder, respects permissions:
     * 1. User.SU status
     * 2. Group membership via `userAccess` table
     * 3. CMS read/write/full
     * 4. Folder Role perms
     * 5. SIS enrollment perms
     *
     * @param User $user
     * @param int  $folderId
     * @param bool $cache Whether to use cached results or get them from DB again
     * @return int
     * @throws InvalidArgumentException TODO implement better annotation/handling for impossible logic exceptions
     * @throws Mapper\Exception         If folder does not exist.
     * @throws LogicException           If unable to build an ExternalUser instance
     * @throws UnexpectedValueException
     */
    public function getFolderAccess(User $user, $folderId, $cache = true): int
    {
        // Special case for root folder
        if ($folderId === Folder\Model::ROOT_ID) {
            // Superusers have FULL access, other users have DENY
            return $user->isSuperuser() ? UserAccess::ACCESS_FULL : UserAccess::ACCESS_DENY;
        }

        // check cache before we proceed for shortcuts
        if ($cache && $this->enableCache && isset($this->cachedUserServicePermissions[$user->getId()][UserAccess::SERVICE_HOMEPAGE][$folderId])) {
            return $this->cachedUserServicePermissions[$user->getId()][UserAccess::SERVICE_HOMEPAGE][$folderId];
        }

        // Find the NNIAN ancestors for the folder
        /** @var Folder\Model $nnian */
        $folder         = $this->folderMapper->findCheaplyById($folderId);
        $nnianAncestors = $this->folderMapper->getNnianAncestors($folder);
        $nnian          = $nnianAncestors->last();
        $nnianId        = $nnian->getId();

        // 1. Superusers always have full access if folder exists
        $accessLevel = $user->isSuperuser() ? UserAccess::ACCESS_FULL : UserAccess::ACCESS_DENY;

        // 2. Check `userAccess` table. (if not already full access)
        if ($accessLevel !== UserAccess::ACCESS_FULL) {
            $accessLevel = max($accessLevel, $this->getUserAccessLevel($user, UserAccess::SERVICE_HOMEPAGE, $nnianId, $cache));
        }

        // 3. Check CMS service access level.  (if not already full access)
        /* Note there is no folderId specified for the CMS service. The effect of this is that if the user
         * has FULL CMS access, we will cache FULL access for whatever folderId is specified.
         */
        $accessLevel = max($accessLevel, $this->getAccessLevel($user, UserAccess::SERVICE_CMS));
        if ($accessLevel !== UserAccess::ACCESS_FULL) {
            $accessLevel = max($accessLevel, $this->getAccessLevel($user, UserAccess::SERVICE_CMS));
        }

        // 4. Folder role permission. (if not already full access)
        if ($accessLevel !== UserAccess::ACCESS_FULL) {
            // Technically this should never increase the permission, as the UI does not support FULL role perms.
            $accessLevel = max($accessLevel, $this->getFolderAccessForRole($user->getRole()->getId(), $folderId, $cache));
        }

        // 5. Sis Class enrollment access. (If not already full access)
        if ($accessLevel !== UserAccess::ACCESS_FULL) {
            $accessLevel = max($accessLevel, $this->getSisAccessLevel($user, $folderId));
        }

        foreach ($nnianAncestors as $ancestor) {
            $this->cachedUserServicePermissions[$user->getId()][UserAccess::SERVICE_HOMEPAGE][$ancestor->getId()] = $accessLevel;
        }

        return $accessLevel;
    }


    /**
     * Returns a role's access to a folder
     *
     * @param int  $roleId
     * @param int  $folderId
     * @param bool $cache
     * @return int
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception If no folder found for specified folder id
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws UnexpectedValueException
     */
    public function getFolderAccessForRole(int $roleId, int $folderId, bool $cache = true): int
    {
        if ($folderId === Folder\Model::ROOT_ID) {
            // All roles implicitly have no access the root pseudo folder
            return UserAccess::ACCESS_DENY;
        }

        // Permission check #1 - Cached results
        if ($cache && $this->enableCache && isset($this->cachedRoleFolderPermissions[$roleId][$folderId])) {
            return $this->cachedRoleFolderPermissions[$roleId][$folderId];
        }

        // Ensure we take role-permission from NNIAN
        /** @var Folder\Model $nnian */
        $folder         = $this->folderMapper->findCheaplyById($folderId);
        $nnianAncestors = $this->folderMapper->getNnianAncestors($folder);
        $nnian          = $nnianAncestors->last();
        $nnianId        = $nnian->getId();

        // Permission check #2 - Query the DB (unique constraints ensure 0:1 results)
        // (it is possible to have a folder that does not inherit that lacks role perms, eg 'Lost & Found' folder)
        /** @var null|RoleAccess $roleAccess */
        $roleAccess      = $this->roleAccessMapper->findByColumnList([
            'cid' => $nnianId,
            'gid' => $roleId,
        ])->first();
        $roleAccessLevel = $roleAccess ? $roleAccess->getType() : UserAccess::ACCESS_DENY;

        foreach ($nnianAncestors as $nnianAncestor) {
            $this->cachedRoleFolderPermissions[$roleId][$nnianAncestor->getId()] = $roleAccessLevel;
        }

        return $roleAccessLevel;
    }


    /**
     * Returns a user's 'userAccess' permission to a service
     *
     * Checks:
     * 1. `userAccess` table
     *
     * @param User $user
     * @param int  $service
     * @param int  $serviceIdentifier
     * @param bool $cache
     * @return int
     * @throws InvalidArgumentException If called with a service identifier for a service that does not use them, or vice versa
     */
    private function getUserAccessLevel(User $user, int $service, int $serviceIdentifier = UserAccess::SID_NONE, bool $cache = true): int
    {
        // Check cache
        if ($cache && $this->enableCache && isset($this->cachedUserServicePermissions[$user->getId()][$service][$serviceIdentifier])) {
            return $this->cachedUserServicePermissions[$user->getId()][$service][$serviceIdentifier];
        }

        // Validate the requested combination of $service and $serviceIdentifier
        if ($serviceIdentifier === UserAccess::SID_NONE) {
            // Even though homepage service requires an id, an id of 0 is valid in this case,
            // and means the root folder. That is a valid invocation of this method.
            if ($service !== UserAccess::SERVICE_HOMEPAGE && in_array($service, UserAccess::requiresServiceIdentifier(), true)) {
                throw new InvalidArgumentException("Service {$service} requires a service identifier to be provided");
            }
        } elseif (in_array($service, UserAccess::doesNotRequireServiceIdentifier(), true)) {
            throw new InvalidArgumentException("Service {$service} does not use service identifiers. Found: {$serviceIdentifier}");
        }

        // Check UserAccess table (there will be 0:1 results due to unique constraint)
        /** @var null|UserAccess $userAccess */
        $userAccess  = $this->userAccessMapper->findByColumnList([
            'uid'     => $user->getId(),
            'service' => $service,
            'sid'     => $serviceIdentifier,
        ])->first();
        $accessLevel = $userAccess ? $userAccess->getAccessLevel() : UserAccess::ACCESS_DENY;

        $this->cachedUserServicePermissions[$user->getId()][$service][$serviceIdentifier] = $accessLevel;
        return $accessLevel;
    }


    /**
     * Returns the user's access level to a folder as defined by SIS enrollment and SB config
     *
     * @param User $user
     * @param int  $folderId
     * @return int
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    private function getSisAccessLevel(User $user, int $folderId): int
    {
        // Only staff and students can have access via SIS involvement
        $role = $user->getRole();
        if (!$role->isStudent() && !$role->isStaff()) {
            return UserAccess::ACCESS_DENY;
        }

        $folderCodeList = $this->folderCodeMapper->findForCategory($folderId);
        // Not involved in any folders? Then no access.
        if (!$folderCodeList->count()) {
            return UserAccess::ACCESS_DENY;
        }
        if ($this->involvedInSubject($user, implode(',', $folderCodeList->getPropertyValues('code')))) {
            return $this->getSisClassRoleTypeAccess($role->getType());
        }
        return UserAccess::ACCESS_DENY;
    }


    /**
     * Lazy loading wrapper around the external user interface to avoid unnecessary external connection
     *
     * @return External_UserInterface
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    private function getExternalUser(): External_UserInterface
    {
        if (!$this->externalUser) {
            $this->externalUser = new External_User();
        }
        return $this->externalUser;
    }
}

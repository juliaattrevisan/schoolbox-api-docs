<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use InvalidArgumentException;
use External_UserInterface;
use External_User;
use LogicException;

/**
 * Responsible for the application of various permission (aka access level) business rules and concepts.
 *
 * Business rules should be implemented here, rather than in the models and mappers to ensure:
 * 1. Cohesive responsibility sets
 * 2. Consistent architecture
 * 3. Testability
 */
class Access
{

    /**
     * Permission/access level constants
     */

    const VISIBILITY_EVERYONE = 0;                 // Accessible by everyone
    const VISIBILITY_STAFF = 1;                    // Accessible by staff only
    const VISIBILITY_STAFF_PARENT = 2;             // Accessible by staff & parents only

    /**
     * Cached permissions
     * Nested array structure $cachedPermissions[uid][service][sid]
     * @var int[][][]
     */
    private $cachedPermissions = [];

    /**
     * Cache folder access calls
     *
     * Indexed by user.id, then folder.id
     *
     * @var int[][]
     */
    protected $cachedFolderAccess = [];

    /**
     * Stores the singleton instance
     *
     * @var array[AccessMapper]
     */
    static protected $instance;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Folder\Code\Mapper */
    private $folderCodeMapper;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var IConfigLoader */
    private $config;


    /**
     * Retrieves the singleton instance of this class.
     *
     * The Mappers *SHOULD* be injected, but not enforced yet to enable backwards compatibility
     *
     * @param UserAccessMapper|null       $userAccessMapper
     * @param RoleAccessMapper|null       $roleAccessMapper
     * @param Folder\Mapper|null          $folderMapper
     * @param Folder\Code\Mapper|null     $folderCodeMapper
     * @param External_UserInterface|null $externalUser
     * @param IConfigLoader|null          $config
     * @return static
     * @throws LogicException If unable to build either an online or offline user connector
     */
    public static function getInstance(
        UserAccessMapper $userAccessMapper = null,
        RoleAccessMapper $roleAccessMapper = null,
        Folder\Mapper $folderMapper = null,
        Folder\Code\Mapper $folderCodeMapper = null,
        External_UserInterface $externalUser = null,
        IConfigLoader $config = null
    ) {
        if (!self::$instance) {
            self::$instance = new static(
                $userAccessMapper ?: UserAccessMapper::getInstance(),
                $roleAccessMapper ?: RoleAccessMapper::getInstance(),
                $folderMapper ?: Folder\Mapper::getInstance(),
                $folderCodeMapper ?: Folder\Code\Mapper::getInstance(),
                $externalUser ?: new External_User(),
                $config ?: ConfigMapper::getInstance()
            );
        }
        return self::$instance;
    }


    /**
     * Private constructor to enforce singleton pattern
     *
     * @param UserAccessMapper       $userAccessMapper
     * @param RoleAccessMapper       $roleAccessMapper
     * @param Folder\Mapper          $folderMapper
     * @param Folder\Code\Mapper     $folderCodeMapper
     * @param External_UserInterface $externalUser
     * @param IConfigLoader          $config
     */
    private function __construct(
        UserAccessMapper $userAccessMapper,
        RoleAccessMapper $roleAccessMapper,
        Folder\Mapper $folderMapper,
        Folder\Code\Mapper $folderCodeMapper,
        External_UserInterface $externalUser,
        IConfigLoader $config
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->folderMapper     = $folderMapper;
        $this->folderCodeMapper = $folderCodeMapper;
        $this->externalUser     = $externalUser;
        $this->config           = $config;
    }


    /**
     * Is the specified user an instructor for the specified folder ?
     *
     * An instructor is any user with WRITE+ permission to a folder
     *
     * @param User   $user
     * @param Folder\Model $folder
     * @return bool
     */
    public function isInstructor(User $user, Folder\Model $folder)
    {
        return $this->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_WRITE;
    }

    /**
     * Is the specified user a learner of the specified folder ?
     *
     * A learner is any user with READ permission to a folder
     *
     * @param User   $user
     * @param Folder\Model $folder
     * @return bool
     */
    public function isLearner(User $user, Folder\Model $folder)
    {
        return $this->getFolderAccess($user, $folder->getId()) == UserAccess::ACCESS_READ;
    }


    /**
     * Returns the minimum access level across the collection of specified folders. Checks perms from:
     * * CMS
     * * GROUP
     * * ROLE
     *
     * @param User  $user
     * @param int[] $folderIds
     * @param bool  $cache
     * @return int
     */
    public function getMinFolderAccess(User $user, array $folderIds, $cache = true)
    {
        if (0 === count($folderIds)) {
            // Technically could throw an InvalidArgException, but this simplifies callers' code.
            return UserAccess::ACCESS_DENY;
        }

        // Intentionally ordered after the empty array check. Not even SU's have an access level to the void.
        if ($user->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        // We must iterate all, rather than from index 1+ (ie fencepost alg.) to robustly account for different indexing
        $minAccess = null;
        foreach ($folderIds as $folderId) {
            $accessLevel = $this->getFolderAccess($user, $folderId, $cache);

            // Short-circuit if any level is DENY. Cannot get less than this.
            if(UserAccess::ACCESS_DENY === $accessLevel) {
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
     * Does not consider SuperUser status.
     *
     * If checking the SERVICE_HOMEPAGE, and the user has different access levels due to their GROUP and SIS permissions,
     * this will return the higher of the two.
     *
     * eg if Zane for some folder has a userAccess.level of READ, but a SIS level of WRITE, then this will return WRITE
     *
     * @param User     $user
     * @param null|int $serviceIdentifier
     * @param int      $service
     * @param bool     $cache
     * @return int
     * @throws InvalidArgumentException If called with a service identifier for a service that does not use them, or vice versa
     * @throws Mapper\Exception         If cannot find an entity for the service identifier (eg, folder not found).
     *                                  Note that we intentionally don't return DENY here, rather, the caller needs to be
     *                                  informed that their call could not be processed for some reason.
     */
    public function getAccessLevel(User $user, int $service, int $serviceIdentifier = UserAccess::SID_NONE, bool $cache = true): int
    {
        // First check the cache
        if ($cache && isset($this->cachedPermissions[$user->getId()][$service][$serviceIdentifier])) {
            return $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier];
        }
        // Validate the requested combination of $service and $serviceIdentifier
        if ($serviceIdentifier === UserAccess::SID_NONE) {
            if (\in_array($service, UserAccess::requiresServiceIdentifier(), true)) {
                throw new InvalidArgumentException("Service {$service} requires a service identifier to be provided");
            }
        } elseif (\in_array($service, UserAccess::doesNotRequireServiceIdentifier(), true)) {
            throw new InvalidArgumentException("Service {$service} does not use service identifiers. Found: {$serviceIdentifier}");
        }
        // Default permission unless something higher is found.
        $accessLevel = UserAccess::ACCESS_DENY;
        // If we are getting the permission for a homepage, we need to take into account inheritance
        if ($service === UserAccess::SERVICE_HOMEPAGE) {
            // Find the NNIAN for the folder
            $folder            = $this->folderMapper->findCheaplyById($serviceIdentifier, ['deleted' => true]);
            $folderNnian       = $this->folderMapper->findNearestNonInheritingAncestralNode($folder);
            $serviceIdentifier = (int)$folderNnian->getId();
        }
        // Check UserAccess table (there will be 0:1 results due to unique constraint)
        $userAccess = $this->userAccessMapper->findByColumnList([
            'uid'     => $user->getId(),
            'service' => $service,
            'sid'     => $serviceIdentifier,
        ])->first();
        if ($userAccess) {
            /** @var UserAccess $userAccess */
            $accessLevel = $userAccess->getAccessLevel();
        }
        // Is checking homepage service, also check SIS-derived permissions
        if ($service === UserAccess::SERVICE_HOMEPAGE) {
            $accessLevel = \max($accessLevel, $this->getSisAccessLevel($user, $serviceIdentifier));
        }
        $this->cachedPermissions[$user->getId()][$service][$serviceIdentifier] = $accessLevel;
        return $accessLevel;
    }


    /**
     * Returns the user's access level to a folder as defined by SIS enrollment and SB config
     *
     * @param User $user
     * @param int  $folderId
     * @return int
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
        if ($this->involvedInSubject($user, \implode(',', $folderCodeList->getPropertyValues('code')))) {
            return $this->getRoleTypeAccess($role);
        }
        return UserAccess::ACCESS_DENY;
    }


    /**
     * Returns the access level a roleType is given when involved with a folder via SIS membership
     *
     * @param string $roleType
     * @return int
     */
    public function getRoleTypeAccess(string $roleType): int
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
     * When passed a schoolbox user ID and subject code (eg. 'CH123') returns true/false to indicate the users
     * involvement in the class
     *
     * @param User   $user
     * @param string $codeString Comma-separated codes, eg 'abc123,xyz456'
     * @return bool
     */
    public function involvedInSubject(User $user, string $codeString): bool
    {
        if (!$user->getExternalId()) {
            return false;
        }
        $externalSubjectList = $this->externalUser->getSubjectList($user);
        if (!\is_array($externalSubjectList) || !\count($externalSubjectList)) {
            return false;
        }

        // Take into account class codes with commas
        $codes = \explode(",", $codeString);
        foreach ($externalSubjectList as $currentSubject) {
            foreach ($codes as $code) {
                // If the code has a '/' it might not contain a campus
                // The class code itself may have a '/' in it. Check for exact match first
                if (\strtolower($currentSubject["code"]) === \strtolower($code)) {
                    return true;
                }
                // Then check if there is a campus mangled in the class code
                if (\strpos($code, '/') !== false) {
                    $codeArray = explode('/', $code, 2);
                    if (\strtolower($currentSubject["code"]) === \strtolower($codeArray[1]) &&
                        \strtolower($currentSubject["campusCode"]) === \strtolower($codeArray[0])
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Check if user is involved in a group
     *
     * @param User $user
     * @param int  $serviceIdentifier
     * @return bool
     */
    public function involvedInGroup(User $user, int $serviceIdentifier): bool
    {
        return $this->userAccessMapper->findByColumnList([
                'service' => UserAccess::SERVICE_HOMEPAGE,
                'sid'     => $serviceIdentifier,
                'uid'     => $user->getId(),
            ])->count() > 0;
    }


    /**
     * Return the specified user's access level for the given folder, respects permissions:
     * * CMS
     * * GROUP
     * * ROLE
     *
     * @param User $user
     * @param int  $folderId
     * @param bool $cache Whether to use cached results or get them from DB again
     * @return int
     */
    public function getFolderAccess(User $user, $folderId, $cache = true)
    {
        // Superusers always have full access
        if ($user->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        /** @var int[] $accessLevels Derived from different permission systems */
        $accessLevels = [];

        /* check cache before we proceed for shortcuts */
        if($cache && isset($this->cachedFolderAccess[$user->getId()][$folderId])){
            return $this->cachedFolderAccess[$user->getId()][$folderId];
        }

        // Check CMS service access level

        /* Note there is no folderId specified for the CMS service. The effect of this is that if the user
         * has FULL CMS access, we will cache FULL access for whatever folderId is specified.
         * We don't need to cache the value locally, we can rely on the Query Cache to do it for us.
         */
        $groupAccessLevel = $this->getAccessLevel($user, UserAccess::SERVICE_CMS);
        $accessLevels[] = $groupAccessLevel;

        // Short-circuit if full access
        if (UserAccess::ACCESS_FULL === $groupAccessLevel) {
            $this->cachedFolderAccess[$user->getId()][$folderId] = UserAccess::ACCESS_FULL;
            return UserAccess::ACCESS_FULL;
        }

        // Check Group access level
        $groupAccessLevel = $this->getAccessLevel($user, UserAccess::SERVICE_HOMEPAGE, (int)$folderId);
        $accessLevels[] = $groupAccessLevel;

        // Short-circuit if full access
        if (UserAccess::ACCESS_FULL === $groupAccessLevel) {
            $this->cachedFolderAccess[$user->getId()][$folderId] = UserAccess::ACCESS_FULL;
            return UserAccess::ACCESS_FULL;
        }

        // Check role access level
        try {
            $roleAccessLevel = $this->roleAccessMapper->getAccess($user, $folderId, $cache)->getType();
            $accessLevels[] = $roleAccessLevel;
        } catch (Mapper\Exception $e) {
            $accessLevels[] = UserAccess::ACCESS_DENY;
        }

        $maxLevel = max($accessLevels);
        return $this->cachedFolderAccess[$user->getId()][$folderId] = $maxLevel;
    }
}

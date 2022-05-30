<?php

namespace Schoolbox\Groups;

use Alaress\Color\ColorFactory;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Resource\Folder\FolderColour;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Timetable_ColourMapper;

class GroupsHelper
{
    //sting const's for enrolment text
    public const ENROLLED    = 'enrolled';
    public const NOTENROLLED = 'notEnrolled';
    public const PENDING     = 'pending';

    public const MEMBERSHIP_NONE = 'none';
    public const MEMBERSHIP_READ = 'read';
    public const MEMBERSHIP_WRITE = 'write';
    public const MEMBERSHIP_ADMIN = 'admin';

    private GroupMapper $groupMapper;
    private UserAccessMapper $userAccessMapper;
    private FolderMapper $folderMapper;
    private Timetable_ColourMapper $timetableColourMapper;
    private RerouteOverrideMapper $reRouteOverrideMapper;

    /**
     * constructor for dependency injection
     */
    public function __construct(
        GroupMapper $groupMapper,
        UserAccessMapper $userAccessMapper,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper
    ) {
        $this->groupMapper              = $groupMapper;
        $this->userAccessMapper         = $userAccessMapper;
        $this->folderMapper             = $folderMapper;
        $this->timetableColourMapper    = $timetableColourMapper;
        $this->reRouteOverrideMapper    = $reRouteOverrideMapper;
    }

    /**
     * get all groups a user is in, and could request or enroll.
     */
    public function getAllGroups(User $user)
    {
        //check if user is currently member
        // Get the groups the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->findGroups($user);
//        $userPerms = $user->getAccessList()->indexBy('serviceIdentifier');

        $groupsArr = $this->groupMapper->getAvailableGroupIds($user);
        //combine to show list of all groups, both available to the user, and those they are enrolled in
        $groupIds = array_unique(array_merge($userPerms->keys(), $groupsArr));

        return $this->buildGroups($user, $groupIds, $userPerms);
    }

    /**
     * get all groups a user is currently enrolled in
     */
    public function getCurrentGroups(User $user)
    {
        //check if user is currently member
        // Get the groups the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->userAccessMapper->findAllGroupsForUser($user)->indexBy('serviceIdentifier');

        return $this->buildGroups($user, $userPerms->keys(), $userPerms);
    }

    /**
     * grabs all groups the user is part of and are following, included any that are force follow
     * originally from `calculateFollowment` in library/Schoolbox/Realtime/Controller.php
     * this is now the only place using this code so it was moved here, and cleaned up.
     *
     * NOTE: this uses the msg_reroute_override table, and that lists groups that have been muted.
     * by default the user will receive notifications for all groups they are a member of, unless they mute them
     * (if those groups are not set to force follow)
     *
     * @param \Schoolbox\User $user
     * @return array
     */
    public function getNotifications(\Schoolbox\User $user): array
    {
        // as much as we'd like get this as cached in session, we can't include subjects here
        $accesses = $this->userAccessMapper->findAllGroupsForUser($user);

        $folderIds = $accesses->getPropertyValues('serviceIdentifier');

        $folders = $this->folderMapper->findByColumn('id', $folderIds)->sortBy('name', 'asc');

        $rerouteOverrides = $this->reRouteOverrideMapper->getMutedFolders($user->getId());

        $muted = [
            'unfollow' => $rerouteOverrides[$user->getId()] ?? [],
            'disabled' => $rerouteOverrides[RerouteOverride::USER_ID_ALL] ?? [],
        ];

        $followment = [];

        // following
        foreach ($folders as $folder) {
            $grp    = $folder->getGroup();
            /**
             * some folders/groups may be broken
             * (sales data folder 62, is apparently a group, but does not have a db entry in userGroups, and has no 'access' set
             * so lets check if the group object / settings exist and if not ignore that folder
             */
            $forced = false;
            if (null !== $grp) {
                $forced = $grp->getForceFollow();
            }

            $disabled = in_array($folder->getId(), $muted['disabled']);
            $follow   = !in_array($folder->getId(), $muted['unfollow']) && !$disabled;

            $followment[$folder->getId()] = [
                'name'     => $folder->getName(),
                'follow'   => $follow,
                'force'    => $forced,
                'disabled' => $disabled,
            ];
        }

        return $followment;
    }

    /**
     * follows / unfollows a group
     * uses have follow (have notifications set) by default
     * they require to opt out.
     * @param int  $group
     * @param User $user
     * @param bool $isNotificationOn
     */
    public function setNotification(int $group, \Schoolbox\User $user, bool $isNotificationOn): void
    {
        //helpers
        $serviceKey = 'service.' . UserAccess::SERVICE_HOMEPAGE . ".$group";

        //see if the object already exists in the db, if so grab it to edit.
        $muteObj = $this->reRouteOverrideMapper->findByColumnList([
                                                                      'msg_route_key' => $serviceKey,
                                                                      'user_id'       => $user->getId(),
                                                                      'delivery'      => RoutePermission::MUTE,
                                                                  ]);

        /**
         * if user DOES NOT want notifications/follow for the group
         * they are currently not already muting the group
         * create the mute object
         */
        if (false === $isNotificationOn && 0 === $muteObj->count()) {
            //create the mute object
            $muteObj = $this->reRouteOverrideMapper->createModel()
                                                   ->setUser($user)
                                                   ->setRouteKey($serviceKey)
                                                   ->setDelivery([RoutePermission::MUTE]);
            $this->reRouteOverrideMapper->save($muteObj);
        }

        /**
         * if user DOES want notifications/follow for the group
         * they are currently muting the group
         * delete the mute object
         */
        if (true === $isNotificationOn && 0 < $muteObj->count()) {
            //delete the mute
            $this->reRouteOverrideMapper->delete($muteObj);
        }
    }

    /**
     * get the text representation of the users enrolment in a group
     * @param null|UserAccess $userGroup
     * @return string
     */
    public function getEnrollmentText(?UserAccess $userGroup): string
    {
        if ($userGroup === null) {
            return self::NOTENROLLED;
        }

        /**
         * if the group perm is ACCESS_DENY that are just pending
         */
        if (UserAccess::ACCESS_DENY === $userGroup->getAccessLevel()) {
            return self::PENDING;
        }

        /**
         * ACCESS_READ perm is enrolled, so that is helpful
         */
        if (UserAccess::ACCESS_READ <= $userGroup->getAccessLevel()) {
            return self::ENROLLED;
        }

        /**
         * to help php stan with is return issue, and to catch any failures of the above
         */
        return self::NOTENROLLED;
    }

    /**
     * build groups object
     */
    private function buildGroups(User $user, array $groupIds, $userPerms = []): array
    {
        //folder colours helper
        $folderColours = new FolderColour($this->timetableColourMapper, new ColorFactory());
        //build notifications (will notify by default, the user will have to opt out)
        $notificationsList = $this->getNotifications($user);

        //build the folder list object with hydration
        $folders = $this->folderMapper->findByColumnList(['id' => $groupIds]);

        // build output array
        $possibleGroups = [];
        foreach ($folders as $folder) {
            /**
             * some folders/groups may be broken
             * (sales data folder 62, is apparently a group, but does not have a db entry in userGroups, and has no 'access' set
             * so lets check if the group object / settings exist and if not ignore that folder
             */
            if (null === $folder->getGroup()) {
                continue;
            }
            /**
             * some groups / folders may have no homepage, and are being used just for data storage
             * lets make sure they are kept hidden from this page (but you can still go and access them directly
             * this is to keep functionality that previously existed in < 21.1.12
             */
            if (null === $folder->getHomepage()) {
                continue;
            }
            /**
             * default favourite to false, as the use is not a member of the group
             * then confirm with the $userPerms array to see if the user has that group as a favourite
             */
            $isFavourite = false;
            if ($userPerms[$folder->getId()] ?? false) {
                $isFavourite = $userPerms[$folder->getId()]->getFavourite();
            }

            //nullable image cause something to have no defaults...
            $imageHash = null;
            if ($folder->getCoverImage()) {
                $imageHash = $folder->getCoverImage()->getHash();
            }
            if (!$folder->getColour()) {
                $folder->setColour($folderColours->getNewColour());
            }

            /**
             * check if user is part of the group, if so use the group perms for the access,
             * if they aren't a member they may have 'read' access via folder perms, but return deny (from group view)
             */
            $access = UserAccess::ACCESS_DENY;
            if ($userPerms[$folder->getId()] ?? false) {
                $access = $userPerms[$folder->getId()]->getAccessLevel();
            }
            /**
             * check if admin
             * admin will have $userPerm[folderID] ACCESS_FULL
             * Superuser will not.
             */
            $isAdmin = false;
            if ($userPerms[$folder->getId()] ?? false) {
                $isAdmin = UserAccess::ACCESS_FULL === $userPerms[$folder->getId()]->getAccessLevel();
            }


            //get group edit access, for the edit link
            if (UserAccess::ACCESS_WRITE <= $access) {
                /**
                $modifyLink = $this->router->generate(
                'folder.getFolderModify',
                ['id' => $folder->getId(), 'folder' => $folder]
                );
                 */
                $modifyLink = sprintf('/resource/folder/%d/modify', $folder->getId());
            } else {
                $modifyLink = false;
            }
            $possibleGroups[] = [
                'id'                => $folder->getId(),
                'name'              => $folder->getName(),
                'enrolmentStatus'   => $this->getEnrollmentText($userPerms[$folder->getId()]),
                'colour'            => $folder->getColour()->getAsHex(),
                'coverImageHash'    => $imageHash,
                'isNotificationsOn' => $notificationsList[$folder->getId()]['follow'] ?? false,
                'isForceFollow'     => $notificationsList[$folder->getId()]['force'] ?? false,
                'isMsgDisabled'     => $notificationsList[$folder->getId()]['disabled'] ?? false,
                'isFavourite'       => $isFavourite,
                'type'              => $folder->getGroup()->getTypeString(),
                'isAccessAdmin'     => $isAdmin,
                'isAccessWrite'     => ($access === UserAccess::ACCESS_WRITE),
                'isAccessRead'      => ($access === UserAccess::ACCESS_READ),
                'isPrivate'         => $folder->getGroup()->isPrivate(),
                'modifyLink'        => $modifyLink,
            ];
        }
        return $possibleGroups;
    }

    /**
     * add user to group
     * @param User  $user
     * @param Group $group
     * @param int   $enrollmentStatus (default: ACCESS_AWAITING_AUTHORISATION)
     * @param int   $membership (default: ACCESS_READ, only has effect if $enrolmentStatus is GroupsHelper::ENROLLED)
     * @param bool  $isFavourite (default: false)
     * @throws \Schoolbox\Mapper\Exception
     */
    public function addToGroup(User $user, Group $group, int $enrollmentStatus = UserAccess::ACCESS_AWAITING_AUTHORISATION, int $membership = UserAccess::ACCESS_READ, bool $isFavourite = false)
    {
        //is awaiting Auth?
        $accessLevel = $enrollmentStatus;
        if (UserAccess::ACCESS_AWAITING_AUTHORISATION !== $accessLevel && UserAccess::ACCESS_DENY !== $accessLevel) {
            $accessLevel = $membership;
        }
        //add the user to the group
        $userGroup = $this->userAccessMapper->createModel();
        $userGroup->setServiceIdentifier($group->getFolder()->getId())
                  ->setUser($user)
                  ->setService(UserAccess::SERVICE_HOMEPAGE)
                  ->setAccessLevel($accessLevel)
                  ->setFavourite($isFavourite); //default to no favourite groups.
        //save group object
        $this->userAccessMapper->save($userGroup);
    }

    /**
     * update params for user / group
     */
    public function updateUserGroup(User $user, Group $group, int $membership, bool $isFavourite, bool $isNotificaionsOn)
    {
        //find user record
        $userGroup = $this->userAccessMapper->findOneByColumnList([
                                                                      'uid' => $user->getId(),
                                                                      'sid' => $group->getFolder()->getId(),
                                                                  ]);
        //update fields
        $userGroup->setFavourite($isFavourite)
            ->setAccessLevel($membership);

        $this->userAccessMapper->save($userGroup);

        /**
         * remove the mute for the user / group this will remove the mute entry from the database.
         */
        $this->setNotification($group->getFolder()->getId(), $user, $isNotificaionsOn);
    }

    /**
     * delete user from group
     */
    public function deleteFromGroup(User $user, Group $group)
    {
        //find user record
        $userGroup = $this->findSingleGroup($user, $group);
        //remove the user group / favourite
        $this->userAccessMapper->delete($userGroup);

        /**
         * remove the mute for the user / group this will remove the mute entry from the database.
         */
        $this->setNotification($group->getFolder()->getId(), $user, true);
    }

    /**
     * sets Favourite
     */
    public function setFavourite(User $user, Group $group, bool $isFavourite)
    {
        $userPerms = $this->findSingleGroup($user, $group);
        $userPerms->setFavourite($isFavourite);
        $this->userAccessMapper->save($userPerms);
    }

    /**
     * gets the standard group output for a single user/group
     */
    public function getGroup(User $user, Group $group)
    {
        // Get the group the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->findSingleGroup($user, $group);
        return $this->buildGroups($user, [$userPerms->getServiceIdentifier()], [$group->getFolder()->getId() => $userPerms]);
    }

    /**
     * checks if the user is a member of the group (not including sis groups)
     */
    public function inGroup(User $user, Group $group): bool
    {
        try {
            $userPerms = $this->findSingleGroup($user, $group);
            return true;
        } catch (NotFound $e) {
            return false;
        }
    }

    /**
     * get only configurable groups (not SIS controlled)
     * @return GenericCollection|UserAccess[]
     */
    private function findGroups(User $user): GenericCollection
    {
        return $this->userAccessMapper->findByColumnList([
                                                                   'uid'     => $user->getId(),
                                                                   'service' => UserAccess::SERVICE_HOMEPAGE
                                                               ])->indexBy('serviceIdentifier');
    }

    /**
     * get the 1 group that is also configurable (not SIS controlled)
     * @param User  $user
     * @param Group $group
     * @return UserAccess
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    private function findSingleGroup(User $user, Group $group): UserAccess
    {
        return $this->userAccessMapper->findOneByColumnList([
                                                             'uid'     => $user->getId(),
                                                             'service' => UserAccess::SERVICE_HOMEPAGE,
                                                             'sid'     => $group->getFolder()->getId()
                                                         ], ['cache' => false]);
    }

    /**
     * convert membership string to membership int for DB use
     * MEMBERSHIP_NONE      => UserAccess::ACCESS_DENY
     * MEMBERSHIP_READ      => UserAccess::ACCESS_READ
     * MEMBERSHIP_WRITE     => UserAccess::ACCESS_WRITE
     * MEMBERSHIP_ADMIN     => UserAccess::ACCESS_FULL
     */
    public static function getMembershipInt(string $membership): int
    {
        $membershipArray = [
            self::MEMBERSHIP_NONE   => UserAccess::ACCESS_DENY,
            self::MEMBERSHIP_READ   => UserAccess::ACCESS_READ,
            self::MEMBERSHIP_WRITE  => UserAccess::ACCESS_WRITE,
            self::MEMBERSHIP_ADMIN  => UserAccess::ACCESS_FULL,
        ];
        if (!isset($membershipArray[$membership])) {
            throw new SchoolboxException("Incorrect membership value");
        }
        return $membershipArray[$membership];
    }

    /**
     * convert membership int to membership string for json use
     * UserAccess::ACCESS_DENY     => MEMBERSHIP_NONE
     * UserAccess::ACCESS_READ     => MEMBERSHIP_READ
     * UserAccess::ACCESS_WRITE    => MEMBERSHIP_WRITE
     * UserAccess::ACCESS_FULL     => MEMBERSHIP_ADMIN
     */
    public static function getMembershipString(int $membership): string
    {
        $membershipArray = [
            UserAccess::ACCESS_DENY     => self::MEMBERSHIP_NONE,
            UserAccess::ACCESS_READ     => self::MEMBERSHIP_READ,
            UserAccess::ACCESS_WRITE    => self::MEMBERSHIP_WRITE,
            UserAccess::ACCESS_FULL     => self::MEMBERSHIP_ADMIN,
        ];
        return $membershipArray[$membership];
    }

    /**
     * convert enrolment string to enrolment int for DB use
     * notEnroled   => UserAccess::ACCESS_DENY
     * pending      => UserAccess::ACCESS_AWAITING_AUTH
     * enroled      => UserAccess::ACCESS_READ
     */
    public static function getEnrolmentInt(string $enrolment): int
    {
        $enrolmentArray = [
            self::NOTENROLLED   => UserAccess::ACCESS_DENY,
            self::PENDING       => UserAccess::ACCESS_AWAITING_AUTHORISATION,
            self::ENROLLED      => UserAccess::ACCESS_READ,
        ];
        if (!isset($enrolmentArray[$enrolment])) {
            throw new SchoolboxException("Incorrect enrolment value");
        }
        return $enrolmentArray[$enrolment];
    }

    /**
     * get membership int from string, usually used in the API
     */
    public function membershipStringToInt(string $membership): int
    {
        switch ($membership) {
            case 'admin':
                return UserAccess::ACCESS_FULL;
            case 'write':
                return UserAccess::ACCESS_WRITE;
            case 'read':
                return UserAccess::ACCESS_READ;
            case 'none':
                return UserAccess::ACCESS_DENY;
            default:
                throw new SchoolboxException("Incorrect membership value");
        }
    }

    /**
     * get user membership string from buildGroup object
     */
    public function getMembershipStringFromGroup(array $groupObject): string
    {
        /**
         * looking for something true in
         * 'isAccessAdmin'     => $isAdmin,
         * 'isAccessWrite'     => ($access === UserAccess::ACCESS_WRITE),
         * 'isAccessRead'      => ($access === UserAccess::ACCESS_READ),
         */

        if ($groupObject['isAccessAdmin']) {
            return self::MEMBERSHIP_ADMIN;
        }
        if ($groupObject['isAccessWrite']) {
            return self::MEMBERSHIP_WRITE;
        }
        if ($groupObject['isAccessRead']) {
            return self::MEMBERSHIP_READ;
        }
        return self::MEMBERSHIP_NONE;
    }
}

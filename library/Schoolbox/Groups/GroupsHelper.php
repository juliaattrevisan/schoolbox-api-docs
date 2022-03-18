<?php

namespace Schoolbox\Groups;

use Alaress\Color\ColorFactory;
use Schoolbox\Permission\Access\Access;
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
    const ENROLLED    = 'enrolled';
    const NOTENROLLED = 'notEnrolled';
    const PENDING     = 'pending';

    private GroupMapper $groupMapper;
    private UserAccessMapper $userAccessMapper;
    private FolderMapper $folderMapper;
    private Access $accessHelper;
    private Timetable_ColourMapper $timetableColourMapper;
    private RerouteOverrideMapper $reRouteOverrideMapper;

    /**
     * constructor for dependency injection
     */
    public function __construct(
        GroupMapper $groupMapper,
        UserAccessMapper $userAccessMapper,
        FolderMapper $folderMapper,
        Access $accessHelper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper
    ) {
        $this->groupMapper              = $groupMapper;
        $this->userAccessMapper         = $userAccessMapper;
        $this->folderMapper             = $folderMapper;
        $this->accessHelper             = $accessHelper;
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
        $userPerms = $user->getAccessList()->indexBy('serviceIdentifier');

        //get list of all possible public groups
        $groups   = $this->groupMapper->findByColumnList([
                                                             'type' => [Group::FREE_GROUP, Group::MODERATED_GROUP],
                                                         ]);
        //combine to show list of all groups, both available to the user, and those they are enrolled in
        $groupIds = array_unique(array_merge($userPerms->keys(), $groups->keys()));

        return $this->buildGroups($user, $groupIds, $userPerms);
    }

    /**
     * get all groups a user is currently enrolled in
     */
    public function getCurrentGroups(User $user)
    {
        //check if user is currently member
        // Get the groups the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->userAccessMapper->findByColumnList([
                                                                   'uid'     => $user->getId(),
                                                                   'service' => UserAccess::SERVICE_HOMEPAGE,
                                                               ])->indexBy('serviceIdentifier');
        $userPerms = $user->getAccessList()->indexBy('serviceIdentifier');

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
        $service = UserAccess::SERVICE_HOMEPAGE;

        $muted = [
            'unfollow' => $user->getId(),
            'disabled' => 0,
        ];

        $routeKeyRegex = "/service\\.$service\\.([0-9]+)/";
        /**
         * loops through all groups the user is part of, and looks to see if they have a mute set in `msg_reroute_override` for that group
         * then will remember that as a muted group
         */
        foreach ($muted as $k => $id) {
            $muted[$k] = $this->reRouteOverrideMapper
                ->getMatchingRoutes("service.$service", RoutePermission::MUTE, $id)
                ->map(
                    function ($override) use ($routeKeyRegex) {
                        /** @var RerouteOverride $override */
                        preg_match($routeKeyRegex, $override->getRouteKey(), $matches);
                        return is_array($matches) ? $matches[1] : null;
                    },
                    false
                )
                ->getAll();
        }

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
                $forced = $grp->getForceFollow() || in_array($folder->getId(), $muted['disabled']);
            }

            $follow = !(in_array($folder->getId(), $muted['unfollow']) || in_array($folder->getId(), $muted['disabled']));

            $followment[$folder->getId()] = [
                'name'   => $folder->getName(),
                'follow' => $follow,
                'force'  => $forced,
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
             * default favourite to false, as the use is not a member of the group
             * then confirm with the $userPerms array to see if the user has that group as a favourite
             */
            $isFavourite = false;
            if ($userPerms[$folder->getId()]) {
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
            if ($userPerms[$folder->getId()]) {
                $access = $userPerms[$folder->getId()]->getAccessLevel();
            }
            /**
             * check if admin
             * admin will have $userPerm[folderID] ACCESS_FULL
             * Superuser will not.
             */
            $isAdmin = false;
            if ($userPerms[$folder->getId()]) {
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
                'isNotificationsOn' => $notificationsList[$folder->getId()]['follow'] ?: false,
                'isForceFollow'     => $notificationsList[$folder->getId()]['force'] ?: false,
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
     */
    public function addToGroup(User $user, Group $group)
    {
        //add the user to the group
        $userGroup = $this->userAccessMapper->createModel();
        $userGroup->setServiceIdentifier($group->getFolder()->getId())
                  ->setUser($user)
                  ->setService(UserAccess::SERVICE_HOMEPAGE)
                  ->setAccessLevel(UserAccess::ACCESS_AWAITING_AUTHORISATION)
                  ->setFavourite(false); //default to no favourite groups.
        //save group object
        $this->userAccessMapper->save($userGroup);
    }

    /**
     * delete user from group
     */
    public function deleteFromGroup(User $user, Group $group)
    {
        //find user record
        $userGroup = $this->userAccessMapper->findOneByColumnList([
                                                                      'uid' => $user->getId(),
                                                                      'sid' => $group->getFolder()->getId(),
                                                                  ]);
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
        $userPerms = $this->userAccessMapper->findOneByColumnList([
                                                                   'uid'     => $user->getId(),
                                                                   'sid'     => $group->getFolder()->getId()
                                                               ]);
        $userPerms->setFavourite($isFavourite);
        $this->userAccessMapper->save($userPerms);
    }

    /**
     * gets the standard group output for a single user/group
     */
    public function getGroup(User $user, Group $group)
    {
        // Get the group the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->userAccessMapper->findByColumnList([
                                                                   'uid'     => $user->getId(),
                                                                   'service' => UserAccess::SERVICE_HOMEPAGE,
                                                                   'sid'     => $group->getFolder()->getId()
                                                               ])->indexBy('serviceIdentifier');
        return $this->buildGroups($user, $userPerms->keys(), $userPerms);
    }

    /**
     * is the user currently part of the group?
     */
    public function isEnrolledInGroup(User $user, Group $group)
    {
        $userPerms = $this->userAccessMapper->findByColumnList([
                                                                      'uid'     => $user->getId(),
                                                                      'sid'     => $group->getFolder()->getId()
                                                                  ]);
        return (bool)count($userPerms);
    }
}

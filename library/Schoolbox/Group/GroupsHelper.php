<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group;

use Alaress\Color\ColorFactory;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Resource\Folder\FolderColour;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;

class GroupsHelper
{
    //sting const's for enrolment text
    public const ENROLED    = 'enroled';
    public const NOTENROLED = 'notEnroled';
    public const PENDING    = 'pending';

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
        $this->groupMapper           = $groupMapper;
        $this->userAccessMapper      = $userAccessMapper;
        $this->folderMapper          = $folderMapper;
        $this->timetableColourMapper = $timetableColourMapper;
        $this->reRouteOverrideMapper = $reRouteOverrideMapper;
    }

    /**
     * get all groups a user is currently enroled in
     * Users can be part of groups that have no homepage, you can return these or ignore them
     * these no homepage groups are usually used for notifications, and are sis controlled
     * @param User $user
     * @return GenericCollection
     */
    public function getCurrentGroups(User $user): GenericCollection
    {
        // Get the groups the user is currently a member, their enrolment type and favourite status
        return $this->getUserGroupAccessList($user, null, false);
    }

    /**
     * Whether group notification is enabled
     * @param int $folderId
     * @return bool
     */
    public function groupNotificationEnabled(int $folderId): bool
    {
        return !$this->reRouteOverrideMapper
            ->isRouteMuted(
                "service." . UserAccess::SERVICE_HOMEPAGE . ".{$folderId}"
            );
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
     * @param User       $user
     * @param Collection $accesses
     */
    private function populateNotification(User $user, Collection $accesses)
    {
        $rerouteOverrides = $this->reRouteOverrideMapper->getMutedFolders($user->getId());

        $muted = [
            'unfollow' => $rerouteOverrides[$user->getId()] ?? [],
            'disabled' => $rerouteOverrides[RerouteOverride::USER_ID_ALL] ?? [],
        ];

        // following
        /** @var UserGroupAccess $access */
        foreach ($accesses as $access) {
            $disabled = in_array($access->getFolder()->getId(), $muted['disabled']);
            $follow   = !in_array($access->getFolder()->getId(), $muted['unfollow']) && !$disabled;
            $access->setFollowing($follow);
            $access->getFolder()->getGroup()->setNotificationDisabled($disabled);
        }
    }

    /**
     * follows / unfollows a group
     * uses have follow (have notifications set) by default
     * they require to opt out.
     * @param int  $folderId
     * @param User $user
     * @param bool $isNotificationOn
     * @return void
     * @throws MapperException
     */
    public function setNotification(int $folderId, User $user, bool $isNotificationOn): void
    {
        //helpers
        $serviceKey = 'service.' . UserAccess::SERVICE_HOMEPAGE . ".$folderId";

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
    public static function getEnrollmentText(?UserAccess $userGroup): string
    {
        if ($userGroup === null) {
            return self::NOTENROLED;
        }

        /**
         * if the group perm is ACCESS_DENY that are just pending
         */
        if (UserAccess::ACCESS_DENY === $userGroup->getAccessLevel()) {
            return self::PENDING;
        }

        /**
         * ACCESS_READ perm is enroled, so that is helpful
         */
        if (UserAccess::ACCESS_READ <= $userGroup->getAccessLevel()) {
            return self::ENROLED;
        }

        /**
         * to help php stan with is return issue, and to catch any failures of the above
         */
        return self::NOTENROLED;
    }

    /**
     * get all groups a user is in, and could request or enroll.
     * @param User $user
     * @return array
     */
    public function getAllGroups(User $user): array
    {
        //check if user is currently member
        // Get the groups the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->getUserGroupAccessList($user, null, false);

        // Gather folders of joined groups
        $joinedFolders = $userPerms->map(static function ($userGroupAccess) {
            /** @var UserGroupAccess $userGroupAccess */
            return $userGroupAccess->getFolder();
        });

        $availableFolderIds = $this->groupMapper->getAvailableGroupIds($user);
        $availableFolderIds = array_diff($availableFolderIds, $joinedFolders->getPropertyValues('id'));
        $availableFolders   = $this->folderMapper->findByColumnList(['id' => $availableFolderIds])->indexBy('id');

        //folder colours helper
        $folderColours = new FolderColour($this->timetableColourMapper, new ColorFactory());

        $folders = $availableFolders->merge($joinedFolders);

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
            /** @var UserGroupAccess|null $userPerm */
            $userPerm = $userPerms->offsetGet($folder->getId());

            //nullable image cause something to have no defaults...
            $imageHash = null;
            if ($folder->getCoverImage()) {
                $imageHash = $folder->getCoverImage()->getHash();
            }
            if (!$folder->getColour()) {
                $folder->setColour($folderColours->getNewColour());
            }

            //get group edit access, for the edit link
            if ($userPerm && UserAccess::ACCESS_WRITE <= $userPerm->getAccessLevel()) {
                /**
                 * $modifyLink = $this->router->generate(
                 * 'folder.getFolderModify',
                 * ['id' => $folder->getId(), 'folder' => $folder]
                 * );
                 */
                $modifyLink = sprintf('/resource/folder/%d/modify', $folder->getId());
            } else {
                $modifyLink = false;
            }
            $possibleGroups[] = [
                'id'                => $folder->getId(),
                'name'              => $folder->getName(),
                'enrolmentStatus'   => $this->getEnrollmentText($userPerm),
                'colour'            => $folder->getColour()->getAsHex(),
                'coverImageHash'    => $imageHash,
                'isNotificationsOn' => $userPerm && $userPerm->getFollowing(),
                'isForceFollow'     => $folder->getGroup()->getForceFollow(),
                'isMsgDisabled'     => $folder->getGroup()->getNotificationDisabled(),
                'isFavourite'       => $userPerm && $userPerm->getFavourite(),
                'type'              => $folder->getGroup()->getTypeString(),
                'isAccessAdmin'     => $userPerm && ($userPerm->getAccessLevel() === UserAccess::ACCESS_FULL),
                'isAccessWrite'     => $userPerm && ($userPerm->getAccessLevel() === UserAccess::ACCESS_WRITE),
                'isAccessRead'      => $userPerm && ($userPerm->getAccessLevel() === UserAccess::ACCESS_READ),
                'isPrivate'         => $folder->getGroup()->isPrivate(),
                'modifyLink'        => $modifyLink,
                'hasHomepage'       => $folder->hasHomepage(),
            ];
        }
        return $possibleGroups;
    }

    /**
     * gets the standard group output for a single user/group
     * Users can be part of groups that have no homepage, you can return these or ignore them
     * these no homepage groups are usually used for notifications, and are sis controlled
     * @param User  $user
     * @param Group $group
     * @return UserGroupAccess|null
     */
    public function getGroup(User $user, Group $group): ?UserGroupAccess
    {
        // Get the group the user is currently a member, their enrolment type and favourite status
        $userPerms = $this->getUserGroupAccessList($user, $group->getFolder(), true);
        if ($userPerms->count() === 0) {
            return null;
        }
        return $userPerms->first();
    }

    /**
     * Get all users in the group
     * @param Group $group
     * @return array
     */
    public function getGroupUsers(Group $group): array
    {
        /** @var UserMapper $userMapper */
        $userMapper     = $this->userAccessMapper->getJoinMapper('user');
        $userAccessList = $this->userAccessMapper->findForFolder($group->getFolder())
                                                 ->indexByCallback(static function (UserAccess $userAccess) {
                                                     return $userAccess->getUser()->getId();
                                                 });

        $memberList = $userMapper->findByColumn('id', $userAccessList->keys());

        $members = [];
        foreach ($memberList as $member) {
            /** @var UserAccess $userAccess */
            $userAccess = $userAccessList->offsetGet($member->getId());
            $userAccess->setUser($member);
            $members[] = (new \Schoolbox\Group\Manage\ModelToArray\UserAccess())($userAccess);
        }
        return $members;
    }

    /**
     * Returns user group list, with notification settings indexed by group folder id
     * @param User        $user
     * @param Folder|null $folder if null, returns all folders
     * @param bool        $excludePending
     * @return GenericCollection
     */
    private function getUserGroupAccessList(User $user, ?Folder $folder, bool $excludePending): GenericCollection
    {
        $param = [
            'uid'     => $user->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
        ];

        if ($excludePending) {
            $param['type'] = [UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL];
        }

        if ($folder) {
            $param['sid'] = $folder->getId();
        }

        $userAccesses = $this->userAccessMapper->findByColumnList($param);

        if ($folder) {
            $folders = $this->folderMapper->createCollection()->add($folder);
        } else {
            $folders = $this->folderMapper->findByColumn('id', $userAccesses->getPropertyValues('serviceIdentifier'))->indexBy('id');
        }
        $folders = $folders->indexBy('id');

        $userGroupAccesses = UserGroupAccess::createCollection();
        foreach ($userAccesses as $userAccess) {
            /** @var Folder|null $folder */
            $folder = $folders->offsetGet($userAccess->getServiceIdentifier());
            if ($folder && $folder->getGroup()) {
                $userGroupAccess = (new UserGroupAccess())
                    ->setServiceIdentifier($userAccess->getServiceIdentifier())
                    ->setService($userAccess->getService())
                    ->setAccessLevel($userAccess->getAccessLevel())
                    ->setFavourite($userAccess->getFavourite())
                    ->setUser($user)
                    ->setFolder($folder);
                $userGroupAccesses->offsetSet($folder->getId(), $userGroupAccess);
            }
        }
        $this->populateNotification($user, $userGroupAccesses);
        return $userGroupAccesses;
    }
}

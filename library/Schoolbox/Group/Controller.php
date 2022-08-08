<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Timetable_ColourMapper;

class Controller extends BaseController
{
    private GroupMapper $groupMapper;

    private UserAccessMapper $userAccessMapper;

    private GroupsHelper $groupsHelper;

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
        $this->groupMapper      = $groupMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->groupsHelper     = new GroupsHelper(
            $groupMapper,
            $userAccessMapper,
            $folderMapper,
            $timetableColourMapper,
            $reRouteOverrideMapper,
        );
    }

    /**
     * Displays all available groups, including public and moderated, and if they are a member private groups
     * included information (notification / favorite)
     *
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @api   GET /resource/newgroups
     * @since 22.0
     */
    public function getNewGroups(): void
    {
        $possibleGroups = $this->groupsHelper->getAllGroups($this->getUser());

        //sort the groups
        usort($possibleGroups, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@groups/groups.twig', [
                'groupList'         => $possibleGroups,
                //send all the endpoint routes, to make it easier for frontend.
                'endpointAdd'       => $this->getRouter()->generate('groups.postMyGroupAdd'),
                'endpointDelete'    => $this->getRouter()->generate('groups.postMyGroupDelete'),
                'endpointNotify'    => $this->getRouter()->generate('groups.postMyGroupNotify'),
                'endpointFavourite' => $this->getRouter()->generate('groups.postMyGroupFavourite'),
            ])
        );
    }

    /**
     * Add self to a group
     * @return void
     * @throws MapperException
     * @api   POST /groups/add
     * @since 22.1
     */
    public function postMyGroupAdd(): void
    {
        //grab the post / user and group objects to make things easier
        $groupFolderId = (int)$this->request->post->get('group');
        $user          = $this->getUser();
        $group         = $this->getGroup($groupFolderId);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }

        /**
         * check if the user is already a member of the group, if so, exit
         * don't trust the groups object in the session, that doesn't update till the user signs-in again.
         */
        try {
            $userGroup = $this->userAccessMapper->findOneByColumnList([
                                                                          'uid'     => $user->getId(),
                                                                          'sid'     => $groupFolderId,
                                                                          'service' => UserAccess::SERVICE_HOMEPAGE,
                                                                      ]);
            //user is already part of the group, error out.
            $this->sendJsonError('User is already part of the group. Please refresh the page to ensure everything is up to date.', 400);
            return; //exit early the user is part of the group.
        } catch (MapperException $e) {
            //check if user is able to be added to the group
            $availableGroups = $this->groupMapper->getAvailableGroupIds($user);
            if (!in_array($groupFolderId, $availableGroups)) {
                $this->sendJsonError('User does not have access to enrol in this group', 400);
                return; //exit early the user is part of the group.
            }
            //add the user to the group
            $userGroup = $this->userAccessMapper->createModel();
            $userGroup->setServiceIdentifier($groupFolderId)
                      ->setUser($user)
                      ->setService(UserAccess::SERVICE_HOMEPAGE)
                      ->setAccessLevel(UserAccess::ACCESS_AWAITING_AUTHORISATION)
                      ->setFavourite(true); //default to favourite
            if ($group->isFree()) {
                $userGroup->setAccessLevel(UserAccess::ACCESS_READ);
            }
            //save group object
            $this->userAccessMapper->save($userGroup);

            $this->getResponse()->content->set(
                $this->buildJsonOutput([
                                           'group'             => $groupFolderId,
                                           'user'              => $user->getId(),
                                           'isFavourite'       => $userGroup->getFavourite(),
                                           'isNotificationsOn' => $this->groupsHelper->groupNotificationEnabled($groupFolderId),
                                           //notifications default to on, you have to opt out in a separate request
                                           'enrolmentStatus'   => $this->groupsHelper->getEnrollmentText($userGroup),
                                       ])
            );
        }
    }

    /**
     * Remove the user from the group
     * @return void
     * @throws MapperException
     * @api   POST /groups/delete
     * @since 22.0
     */
    public function postMyGroupDelete(): void
    {
        //grab the post / user and group objects to make things easier
        $groupFolderId = (int)$this->request->post->get('group');
        $user          = $this->getUser();
        $group         = $this->getGroup($groupFolderId);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $groupFolderId);
        if (null === $userGroup) {
            return; //exit early the user is not part of this group
        }

        if ($group->isPrivate()) {
            $this->sendJsonError('No Permission to leave group', 400);
            return;
        }
        //remove the user group / favourite
        $this->userAccessMapper->delete($userGroup);

        /**
         * remove the mute for the user / group this will remove the mute entry from the database.
         */
        $this->groupsHelper->setNotification($groupFolderId, $user, true);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'user'            => $user->getId(),
                                       'group'           => $groupFolderId,
                                       'action'          => 'delete',
                                       'enrolmentStatus' => GroupsHelper::NOTENROLED,
                                   ])
        );
    }

    /**
     * Edit the user notification preferences
     * @return void
     * @throws MapperException
     * @api   POST /groups/notify
     * @since 22.0
     */
    public function postMyGroupNotify(): void
    {
        //grab the post / user and group objects to make things easier
        $groupFolderId    = (int)$this->request->post->get('group');
        $isNotificationOn = (bool)$this->request->post->get('isNotificationsOn');
        $user             = $this->getUser();
        $group            = $this->getGroup($groupFolderId);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $groupFolderId);
        if (null === $userGroup) {
            return;  //exit early the user is not part of this group
        }

        /**
         * add/remove notifications
         * check if the user can actually unsubscribe from notifications
         * if they are unable to error and exit early
         */
        if ($group->getForceFollow()) {
            $this->sendJsonError('No Permission to unfollow group', 400);
            return;
        }

        //update the notification to what the user sets.
        $this->groupsHelper->setNotification($group->getFolder()->getId(), $user, $isNotificationOn);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'group'             => $groupFolderId,
                                       'user'              => $user->getId(),
                                       'isNotificationsOn' => $isNotificationOn,
                                       'enrolmentStatus'   => $this->groupsHelper->getEnrollmentText($userGroup),
                                   ])
        );
    }

    /**
     * Edit group favourite
     * @return void
     * @throws MapperException
     * @api   POST /groups/favourite
     * @since 22.0
     */
    public function postMyGroupFavourite(): void
    {
        //grab the post / user and group objects to make things easier
        $groupFolderId = (int)$this->request->post->get('group');
        $isFavourite   = (bool)$this->request->post->get('isFavourite');
        $user          = $this->getUser();
        $group         = $this->getGroup($groupFolderId);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $groupFolderId);
        if (null === $userGroup) {
            return;  //exit early the user is not part of this group
        }

        //make/remove favourite
        $userGroup->setFavourite($isFavourite);

        //save group object
        $this->userAccessMapper->save($userGroup);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'group'           => $groupFolderId,
                                       'user'            => $user->getId(),
                                       'isFavourite'     => $userGroup->getFavourite(),
                                       'enrolmentStatus' => $this->groupsHelper->getEnrollmentText($userGroup),
                                   ])
        );
    }

    /**
     * grab group objects with error checking:
     * does the group actually exist?
     *
     * @param int $groupId
     * @return Group|null
     */
    private function getGroup(int $groupId): ?Group
    {
        try {
            return $this->groupMapper->findById($groupId);
        } catch (MapperException $e) {
            $this->sendJsonError('Group does not exist', 400);
            return null;
        }
    }

    /**
     * get the user group object
     *
     * check if the user is already a member of the group, if so, return the object
     * else return an error as they are not a member, and exit
     *
     * don't trust the groups object in the session, that doesn't update till the user signs-in again.
     *
     * @param User $user
     * @param int  $groupId
     * @return null|UserAccess
     */
    private function getUserGroup(User $user, int $groupId): ?UserAccess
    {
        try {
            return $this->userAccessMapper->findOneByColumnList([
                                                                    'uid'     => $user->getId(),
                                                                    'sid'     => $groupId,
                                                                    'service' => UserAccess::SERVICE_HOMEPAGE,
                                                                ]);
        } catch (MapperException $e) {
            $this->sendJsonError('User is not part of the group', 400);
            return null;
        }
    }
}

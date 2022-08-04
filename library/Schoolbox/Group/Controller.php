<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Group;

use Alaress\Web\Controller\Base as BaseController;
use Crell\ApiProblem\ApiProblem;
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
        $this->groupMapper              = $groupMapper;
        $this->userAccessMapper         = $userAccessMapper;
        $this->groupsHelper = new GroupsHelper(
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
     * @api GET /resource/newgroups
     */
    public function getNewGroups(): void
    {
        $user = $this->getUser();

        $possibleGroups = $this->groupsHelper->getAllGroups($user);

        //sort the groups
        usort($possibleGroups, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@groups/groups.twig', [
                'groupList' => $possibleGroups,
                //send all the endpoint routes, to make it easier for frontend.
                'endpointAdd'  => $this->getRouter()->generate('groups.postMyGroupAdd'),
                'endpointDelete'  => $this->getRouter()->generate('groups.postMyGroupDelete'),
                'endpointNotify'  => $this->getRouter()->generate('groups.postMyGroupNotify'),
                'endpointFavourite'  => $this->getRouter()->generate('groups.postMyGroupFavourite'),
            ])
        );
    }

    /**
     * @api POST /groups/add
     */
    public function postMyGroupAdd(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }

        /**
         * check if the user is already a member of the group, if so, exit
         * don't trust the groups object in the session, that doesn't update till the user signs-in again.
         */
        try {
            $userGroup = $this->userAccessMapper->findOneByColumnList([
                                                                          'uid' => $user->getId(),
                                                                          'sid' => $post['group'],
                                                                          'service' => UserAccess::SERVICE_HOMEPAGE
                                                                      ]);
            //user is already part of the group, error out.
            $problem = (new ApiProblem('User is already part of the group. Please refresh the page to ensure everything is up to date.'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        } catch (MapperException\NotFound $e) {
            //check if user is able to be added to the group
            $availableGroups = $this->groupMapper->getAvailableGroupIds($user);
            if (!in_array($post['group'], $availableGroups)) {
                $problem = (new ApiProblem('User does not have access to enrol in this group'))
                    ->setStatus(400)
                    ->asArray();
                $this->getResponse()->status->setCode(400);
                $this->getResponse()->content->set($this->buildJsonOutput($problem));
                return; //exit early the user is part of the group.
            }
            //add the user to the group
            $userGroup = $this->userAccessMapper->createModel();
            $userGroup->setServiceIdentifier($post['group'])
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
                                           'group'             => $post['group'],
                                           'user'              => $user->getId(),
                                           'isFavourite'       => $userGroup->getFavourite(),
                                           'isNotificationsOn' => true,
                                           //notifications default to on, you have to opt out in a separate request
                                           'enrolmentStatus'   => $this->groupsHelper->getEnrollmentText($userGroup),
                                       ])
            );
        }
    }

    /**
     * remove the user from the group
     * @api POST /groups/delete
     */
    public function postMyGroupDelete(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $post['group']);
        if (null === $userGroup) {
            return; //exit early the user is not part of this group
        }

        if ($group->isPrivate()) {
            $problem = (new ApiProblem('No Permission to leave group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return;
        }
        //remove the user group / favourite
        $this->userAccessMapper->delete($userGroup);

        /**
         * remove the mute for the user / group this will remove the mute entry from the database.
         */
        $this->groupsHelper->setNotification($post['group'], $user, true);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'user'            => $user->getId(),
                                       'group'           => $post['group'],
                                       'action'          => 'delete',
                                       'enrolmentStatus' => GroupsHelper::NOTENROLED,
                                   ])
        );
    }

    /**
     * edit the user notification preferences
     * @api POST /groups/notify
     */
    public function postMyGroupNotify(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $post['group']);
        if (null === $userGroup) {
            return;  //exit early the user is not part of this group
        }

        /**
         * add/remove notifications
         * check if the user can actually unsubscribe from notifications
         * if they are unable to error and exit early
         */
        if ($group->getForceFollow()) {
            $problem = (new ApiProblem('No Permission to unfollow group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return;
        }

        //update the notification to what the user sets.
        $this->groupsHelper->setNotification($group->getFolder()->getId(), $user, (bool)$post['isNotificationsOn']);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'group'             => $post['group'],
                                       'user'              => $user->getId(),
                                       'isNotificationsOn' => (bool)$post['isNotificationsOn'],
                                       'access'            => $this->groupsHelper->getEnrollmentText($userGroup),
                                   ])
        );
    }

    /**
     * edit group favourite
     */
    public function postMyGroupFavourite(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $post['group']);
        if (null === $userGroup) {
            return;  //exit early the user is not part of this group
        }

        //make/remove favourite
        $userGroup->setFavourite((bool)$post['isFavourite']);

        //save group object
        $this->userAccessMapper->save($userGroup);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'group'             => $post['group'],
                                       'user'              => $user->getId(),
                                       'isFavourite'       => $userGroup->getFavourite(),
                                       'access'            => $this->groupsHelper->getEnrollmentText($userGroup),
                                   ])
        );
    }

    /**
     * grab group objects with error checking:
     * does the group actually exist?
     *
     * @param null|int $groupId
     * @return null | Group
     */
    protected function getGroup(?int $groupId): ?Group
    {
        try {
            /** @var Group $group */
            $group = $this->groupMapper->findById($groupId);
            return $group;
        } catch (MapperException\NotFound $e) {
            $problem = (new ApiProblem('Group does not exist'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
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
    protected function getUserGroup(User $user, int $groupId): ?UserAccess
    {
        try {
            return $this->userAccessMapper->findOneByColumnList([
                                                                    'uid' => $user->getId(),
                                                                    'sid' => $groupId,
                                                                ]);
        } catch (MapperException\NotFound $e) {
            $problem = (new ApiProblem('User is not part of the group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return null;
        }
    }
}

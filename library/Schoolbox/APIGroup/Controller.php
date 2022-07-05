<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Collection;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;

class Controller extends Base
{
    private GroupsHelper $groupsHelper;
    protected UserMapper $userMapper;

    /**
     * Controller constructor.
     *
     * @param UserAccessMapper       $userAccessMapper
     * @param GroupMapper            $groupMapper
     * @param FolderMapper           $folderMapper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param RerouteOverrideMapper  $reRouteOverrideMapper
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper
    ) {
        $this->groupsHelper = new GroupsHelper(
            $groupMapper,
            $userAccessMapper,
            $folderMapper,
            $timetableColourMapper,
            $reRouteOverrideMapper,
        );
    }

    /**
     * Returns group memberships for a user
     *
     * @param User $user
     * @api /user/{userId}/group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipSearch
     */
    public function apiMembership(User $user)
    {

        $users_groups = $this->groupsHelper->getCurrentGroups($user);
        $apiGroups = new Collection($users_groups);
        $output = $apiGroups->map(new ModelToArray());
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * Returns group membership for a user
     *
     * @param User $user
     * @param Group $group
     * @api GET /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipGet
     */
    public function apiMembershipGet(User $user, Group $group)
    {
        if (!$this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is not Enroled in the Group'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        $users_groups = $this->groupsHelper->getGroup($user, $group);
        $apiGroups    = new Collection($users_groups);
        $output = $apiGroups->map(new ModelToArray());
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * adds a user into a group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPost
     * @api POST /user/{userId}/group/{groupId}
     */
    public function apiMembershipAdd(User $user, Group $group)
    {
        if ($this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is already Enroled in the Group'))
                ->setStatus(409)
                ->asArray();
            $this->getResponse()->status->setCode(409);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        $post = json_decode(json_encode($this->request->content->get()), true);
        /**
         * check post array if missing any, lets error out...
         */
        if (
            !isset($post['membership'], $post['isNotificationsOn'], $post['isFavourite'])
        ) {
            $problem = (new ApiProblem('Not all fields are filled'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the api does not have enough data to work correctly.
        }
        $post['membership']        = $this->groupsHelper->getMembershipInt($post['membership']);
        //check if user is trying to break the system by deleting a user via the PUT command, and error
        $post['enrolmentStatus'] = $post['enrolmentStatus'] ?? GroupsHelper::ENROLED;
        if (GroupsHelper::NOTENROLED === $post['enrolmentStatus']  || GroupsHelper::MEMBERSHIP_NONE === $post['membership']) {
            $problem = (new ApiProblem('Unable to remove a user from this endpoint, please use the DELETE method'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early this API wont delete a user...
        }
        $post['isNotificationsOn'] = $group->getForceFollow() ? true : $post['isNotificationsOn'];
        $post['isFavourite']       = $post['isFavourite'] ?? false;
        $post['enrolmentStatus']   = $this->groupsHelper->getEnrolmentInt($post['enrolmentStatus']);

        $this->groupsHelper->addToGroup($user, $group, $post['enrolmentStatus'], $post['membership'], $post['isFavourite']);
        $this->groupsHelper->setNotification($group->getFolder()->getId(), $user, $post['isNotificationsOn']);
        $users_groups = $this->groupsHelper->getGroup($user, $group);
        $apiGroups    = new Collection($users_groups);
        $output       = $apiGroups->map(new ModelToArray());
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * updated all group field for a user
     *
     * @param User $user
     * @param Group $group
     * @api PUT /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPut
     */
    public function apiMembershipPut(User $user, Group $group)
    {
        if (!$this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is not Enroled in the Group'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        $post = json_decode(json_encode($this->request->content->get()), true);
        /**
         * check post array if missing any, lets error out...
         */
        if (
            !isset($post['membership'], $post['isNotificationsOn'], $post['isFavourite'])
        ) {
            $problem = (new ApiProblem('Not all fields are filled'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the api does not have enough data to work correctly.
        }

        $defaultData = [
            'membership'        => UserAccess::ACCESS_READ,
            'isNotificationsOn' => true,
            'isFavourite'       => false,
            'enrolmentStatus'   => GroupsHelper::ENROLED,
        ];
        $this->processGroupPostData($user, $group, $post, $defaultData);
    }

    /**
     * updated selected group field for a user
     *
     * @param User $user
     * @param Group $group
     * @api PATCH /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPatch
     */
    public function apiMembershipPatch(User $user, Group $group)
    {
        if (!$this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is not Enroled in the Group'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        $users_group = $this->groupsHelper->getGroup($user, $group)[0];
        $post = json_decode(json_encode($this->request->content->get()), true);
        $defaultData = [
            'membership'        => $this->groupsHelper->getMembershipStringFromGroup($users_group),
            'isNotificationsOn' => $users_group['isNotificationsOn'],
            'isFavourite'       => $users_group['isFavourite'],
            'enrolmentStatus'   => $users_group['enrolmentStatus'],
        ];
        $this->processGroupPostData($user, $group, $post, $defaultData);
    }

    private function processGroupPostData(User $user, Group $group, array $post, array $defaultData): void
    {
        if (!$this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is not Enroled in the Group'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        //check if user is trying to break the system by deleting a user via the PUT command, and error
        if (GroupsHelper::NOTENROLED === $post['enrolmentStatus']  || GroupsHelper::MEMBERSHIP_NONE === $post['membership']) {
            $problem = (new ApiProblem('Unable to remove a user from this endpoint, please use the DELETE method'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early this API wont delete a user...
        }

        $post['membership'] = $this->groupsHelper->membershipStringToInt($post['membership'] ?? $defaultData['membership']);
        $post['isNotificationsOn'] = $post['isNotificationsOn'] ?? $defaultData['isNotificationsOn'];
        $post['isFavourite'] = $post['isFavourite'] ?? $defaultData['isFavourite'];
        $post['enrolmentStatus'] = $this->groupsHelper->getEnrolmentInt($post['enrolmentStatus'] ?? $defaultData['enrolmentStatus']);

        /**
         * check if we should be updating the users enrolment / membership
         * if enrolment status is set, and is Enroled / awaiting auth, then lets change the membership perms
         */
        if (UserAccess::ACCESS_AWAITING_AUTHORISATION === $post['enrolmentStatus']) {
            //if set to awaiting auth, set to deny (which is in the groups system awaiting auth)
            $post['membership'] = UserAccess::ACCESS_DENY;
        }
        if (UserAccess::ACCESS_READ === $post['enrolmentStatus']) {
            /**
             * it should grab the higher perm either membership or read
             * $post['membership'] could be none, read, write or admin, so need to check, not just assume.
             */
            $post['membership'] = max($post['membership'], UserAccess::ACCESS_READ);
        }

        //update details
        $this->groupsHelper->updateUserGroup($user, $group, $post['membership'], $post['isFavourite'], $post['isNotificationsOn']);

        //get data from save
        $users_groups = $this->groupsHelper->getGroup($user, $group);
        $apiGroups = new Collection($users_groups);
        $output = $apiGroups->map(new ModelToArray());
        //output
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * Deletes a user's group membership
     * @api DELETE /user/{userId}/group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipDelete
     */
    public function apiMembershipDelete(User $user, Group $group)
    {
        if (!$this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is not Enroled in the Group'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        try {
            $this->groupsHelper->deleteFromGroup($user, $group);
            $this->getResponse()->status->setCode(204);
        } catch (Exception $e) {
            $problem = (new ApiProblem('Unable to remove the user from the group.'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
        }
    }
}

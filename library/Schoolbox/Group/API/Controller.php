<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\API;

use Alaress\Web\Controller\Base;
use Schoolbox\Group\GroupsHelper;
use Schoolbox\Group\UserGroupAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Timetable_ColourMapper;

class Controller extends Base
{
    private GroupsHelper $groupsHelper;

    private UserAccessMapper $userAccessMapper;

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
     * Returns group memberships for a user
     *
     * @param User $user
     * @api  /user/{userId}/group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipSearch
     */
    public function apiMembership(User $user)
    {
        $userAccesses = $this->groupsHelper->getCurrentGroups($user);
        $output       = $userAccesses->map(new ModelToArray());
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * Returns group membership for a user
     *
     * @param User  $user
     * @param Group $group
     * @api  GET /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipGet
     */
    public function apiMembershipGet(User $user, Group $group)
    {
        $userAccess = $this->groupsHelper->getGroup($user, $group);
        if (!$userAccess) {
            $this->sendJsonError('User is not enroled in the group', 404);
            return;
        }
        $output = (new ModelToArray())($userAccess);
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * adds a user into a group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPost
     * @api  POST /user/{userId}/group/{groupId}
     */
    public function apiMembershipAdd(User $user, Group $group)
    {
        $userAccess = $this->groupsHelper->getGroup($user, $group);
        if ($userAccess && $userAccess->getAccessLevel() !== 0) {
            $this->sendJsonError('User is already enroled in the group', 409);
            return;
        }
        $userAccess = (new UserGroupAccess())
            ->setServiceIdentifier($group->getFolder()->getId())
            ->setUser($user)
            ->setFolder($group->getFolder())
            ->setService(UserAccess::SERVICE_HOMEPAGE);

        $this->processGroupPostData($userAccess, $user, $group);
    }

    /**
     * updated all group field for a user
     *
     * @param User  $user
     * @param Group $group
     * @api  PUT /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPut
     */
    public function apiMembershipPut(User $user, Group $group)
    {
        $userAccess = $this->groupsHelper->getGroup($user, $group);
        if (!$userAccess) {
            $this->sendJsonError('User does not exist in the group', 409);
            return;
        }
        $this->processGroupPostData($userAccess, $user, $group);
    }

    /**
     * updated selected group field for a user
     *
     * @param User  $user
     * @param Group $group
     * @api  PATCH /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPatch
     */
    public function apiMembershipPatch(User $user, Group $group)
    {
        $userAccess = $this->groupsHelper->getGroup($user, $group);
        if (!$userAccess) {
            $this->sendJsonError('User does not exist in the group', 409);
            return;
        }
        $this->processGroupPostData($userAccess, $user, $group);
    }

    /**
     * Apply post data to given userAccess and persist
     * @param UserGroupAccess $userAccess
     * @param User       $user
     * @param Group      $group
     * @return void
     */
    private function processGroupPostData(UserGroupAccess $userAccess, User $user, Group $group)
    {
        $post = json_decode(json_encode($this->request->content->get()), true);

        try {
            /** @var UserGroupAccess $newAccess */
            $newAccess = (new ArrayToModel())($post ?? [], $userAccess);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 401);
            return;
        }

        try {
            $this->userAccessMapper->save($newAccess);
        } catch (MapperException $e) {
            $this->sendJsonError($e->getMessage(), $e->getCode());
            return;
        }

        if (!$group->getForceFollow()) {
            try {
                $this->groupsHelper->setNotification($group->getFolder()->getId(), $user, $newAccess->getFollowing());
            } catch (MapperException $e) {
                $this->sendJsonError($e->getMessage(), $e->getCode());
                return;
            }
        }
        $output = (new ModelToArray())($newAccess);
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * Deletes a user's group membership
     * @api  DELETE /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipDelete
     */
    public function apiMembershipDelete(User $user, Group $group)
    {
        $userAccess = $this->groupsHelper->getGroup($user, $group);
        if (!$userAccess) {
            $this->sendJsonError('User is not enroled in the group', 404);
            return;
        }

        try {
            $this->userAccessMapper->delete($userAccess);
            $this->getResponse()->status->setCode(204);
        } catch (MapperException $e) {
            $this->sendJsonError('Unable to remove the user from the group.', 400);
        }
    }
}

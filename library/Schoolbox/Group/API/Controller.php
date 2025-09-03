<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\API;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
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
     * @param \Alaress\Authorizer\Authorizer $authorizer
     * @param \Aura\Router\Generator $routeGenerator
     * @param UserAccessMapper       $userAccessMapper
     * @param GroupMapper            $groupMapper
     * @param FolderMapper           $folderMapper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param RerouteOverrideMapper  $reRouteOverrideMapper
     */
    public function __construct(
        \Alaress\Authorizer\Authorizer $authorizer,
        \Aura\Router\Generator $routeGenerator,
        UserAccessMapper $userAccessMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper
    ) {
        $this->userAccessMapper = $userAccessMapper;
        $this->groupsHelper     = new GroupsHelper(
            $authorizer,
            $routeGenerator,
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
     * @api  GET /user/{userId}/group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipSearch
     */
    public function apiMembership(User $user)
    {
        $userAccesses = $this->groupsHelper->getCurrentGroups($user);
        $output       = $userAccesses->map(new ModelToArray());

        // Convert from Collection to Array, and drop keys.  Keys are dropped because the key data is also contained in
        // the array elements, and to ensure that the JSON container will be an array instead of an object.
        // E.g. We want    JSON like this: [{id: 123, ...}, {id: 456, ...}]
        //      instead of JSON like this: {123: {id: 123, ...}, 456: {id: 456, ...}}
        $output->reindex();

        $this->response = $this->responseAsJson($output);
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
        $output         = (new ModelToArray())($userAccess);
        $this->response = $this->responseAsJson($output);
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
        if (!$userAccess) {
            $userAccess = new UserGroupAccess();
            $userAccess->setServiceIdentifier($group->getFolder()->getId())
                       ->setUser($user)
                       ->setFolder($group->getFolder())
                       ->setService(UserAccess::SERVICE_HOMEPAGE);
        }
        $this->processGroupPostData($userAccess, $user, $group, false, 201);
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
        $this->processGroupPostData($userAccess, $user, $group, false, 200);
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
        $this->processGroupPostData($userAccess, $user, $group, true, 200);
    }

    /**
     * Apply post data to given userAccess and persist
     * @param UserGroupAccess $userAccess
     * @param User            $user
     * @param Group           $group
     * @param bool            $isPatch set false to check full parameters
     * @param int             $responseCode
     * @return void
     */
    private function processGroupPostData(UserGroupAccess $userAccess, User $user, Group $group, bool $isPatch, int $responseCode)
    {
        $post = $this->request->getParsedBody();

        try {
            /** @var UserGroupAccess $newAccess */
            $newAccess = (new ArrayToModel())($post ?? [], $userAccess, !$isPatch);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 401);
            return;
        }

        if (
            $userAccess->getAccessLevel() === UserAccess::ACCESS_FULL
            && !$user->getRole()->isStaff()
            && $this->getConfigLoader()->get(ConfigMapper::NAME_GROUP_STAFF_ONLY)
        ) {
            $this->sendJsonError('Non staff cannot be a group manager', 401);
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
        $output         = (new ModelToArray())($newAccess);
        $this->response = $this->responseAsJson($output)->withStatus($responseCode);
    }

    /**
     * Deletes a user's group membership
     * @api  DELETE /user/{userId}/group/{groupId}
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipDelete
     */
    public function apiMembershipDelete(User $user, Group $group)
    {
        // Need to get UserAccess rather than UserGroupAccess
        $userAccesses = $this->userAccessMapper->findForFolderUser($group->getFolder(), User::createCollection()->add($user));

        if (!$userAccesses->count()) {
            $this->sendJsonError('User is not enroled in the group', 404);
            return;
        }

        try {
            $this->userAccessMapper->delete($userAccesses);
            $this->response = $this->responseEmpty(204);
        } catch (MapperException $e) {
            $this->sendJsonError('Unable to remove the user from the group.', 400);
        }
    }
}

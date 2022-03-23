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
use Schoolbox\APIGroup\ModelToArray;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\UserAccess;
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
     * adds a user into a group
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipPost
     * @api POST /user/{userId}/group/{groupId}
     */
    public function apiMembershipAdd(User $user, Group $group)
    {
        if ($this->groupsHelper->inGroup($user, $group)) {
            $problem = (new ApiProblem('User is already Enrolled in the Group'))
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
        $post['membership'] = $this->groupsHelper->getMembershipInt($post['membership']);
        $post['isNotificationsOn'] = $group->getForceFollow() ? true : $post['isNotificationsOn'];
        $post['isFavourite'] = $post['isFavourite'] ?? false;
        $post['enrolmentStatus'] = $post['enrolmentStatus'] ?? UserAccess::ACCESS_READ;

        $this->groupsHelper->addToGroup($user, $group, $post['enrolmentStatus'], $post['membership'], $post['isFavourite']);
        $this->groupsHelper->setNotification($group->getFolder()->getId(), $user, $post['isNotificationsOn']);
        $users_groups = $this->groupsHelper->getGroup($user, $group);
        $apiGroups = new Collection($users_groups);
        $output = $apiGroups->map(new ModelToArray());
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}

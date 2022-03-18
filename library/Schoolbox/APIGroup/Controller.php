<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\APIGroup\ModelToArray;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\Access;
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
     * @param Access                 $accessHelper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param RerouteOverrideMapper  $reRouteOverrideMapper
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Access $accessHelper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper
    ) {
        $this->groupsHelper = new GroupsHelper(
            $groupMapper,
            $userAccessMapper,
            $folderMapper,
            $accessHelper,
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
     * @api POST /apigroup/{userId}/groupMembership/{groupId}
     */
    public function apiMembershipAdd(User $user, Group $group)
    {
        $post = $this->getRequest()->post->getArrayCopy();
        $post['membership'] = $post['membership'] ?: UserAccess::ACCESS_READ;
        $post['isNotificationsOn'] = $post['isNotificationsOn'] ?: false;
        $post['isFavourite'] = $post['isFavourite'] ?: false;
        $post['enrollmentStatus'] = $post['enrollmentStatus'] ?: UserAccess::ACCESS_AWAITING_AUTHORISATION;

        $this->groupsHelper->addToGroup($user, $group, $post['enrollmentStatus'], $post['membership'], $post['isFavourite']);
        $this->groupsHelper->setNotification($group->getFolder()->getId(), $user, $post['isNotificationsOn']);
        $users_groups = $this->groupsHelper->getGroup($user, $group);
        $apiGroups = [];
        foreach ($users_groups as $group) {
            $membership = 'read';
            if ($group['isAccessWrite']) {
                $membership = 'write';
            } elseif ($group['isAccessAdmin']) {
                $membership = 'admin';
            }
            $apiGroups[] = [
                'id'                => $group['id'],
                'name'              => $group['name'],
                'type'              => $group['type'],
                'isForceFollow'     => $group['isForceFollow'],
                'isNotificationsOn' => $group['isNotificationsOn'],
                'isPrivate'         => $group['isPrivate'],
                'enrolmentStatus'   => $group['enrolmentStatus'],
                'membership'        => $membership,
                '_links'            => [
                    'homepage'      => sprintf('/homepage/%d', $group['id'])
                ]
            ];
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                $apiGroups
            )
        );
    }
}

<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Alaress\Web\Controller\Base;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
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
     * @api /user/{userId}/groupMembership
     * @link http://api.schoolbox.com.au/#operation/userGroupMembershipSearch
     */
    public function apiMembership(User $user)
    {

        $users_groups = $this->groupsHelper->getCurrentGroups($user);
        $apiGroups = [];
        foreach ($users_groups as $group) {
            $membership = 'none';
            if ($group['isAccessRead']) {
                $membership = 'read';
            } elseif ($group['isAccessWrite']) {
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
                'enrollmentStatus'   => $group['enrolmentStatus'],
                'membership'        => $membership,
                '_links'            => [
                    'homepage'      => sprintf('/homepage/%d', $group['id'])
                ]
            ];
        }

        $this->response->content->set(
            $this->buildJsonOutput(
                $apiGroups
            )
        );
    }
}

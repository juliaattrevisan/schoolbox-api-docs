<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser;

use Alaress\Web\Controller\Base;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\APIGroup\ModelToArray as GroupArray;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;

class Controller extends Base
{
    private GroupsHelper $groupsHelper;
    protected UserMapper $userMapper;

    /**
     * External_UserInterface
     */
    private External_UserInterface $externalUser;

    /**
     * Controller constructor.
     *
     * @param UserAccessMapper       $userAccessMapper
     * @param GroupMapper            $groupMapper
     * @param FolderMapper           $folderMapper
     * @param Access                 $accessHelper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param RerouteOverrideMapper  $reRouteOverrideMapper
     * @param External_UserInterface $externalUser
     * @param UserMapper             $userMapper
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Access $accessHelper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper,
        External_UserInterface $externalUser,
        UserMapper $userMapper
    ) {
        $this->externalUser = $externalUser;
        $this->userMapper = $userMapper;
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
     * Returns user object
     *
     * @param User $user
     * @api /apiuser/{userId}
     * @link http://api.schoolbox.com.au/#operation/userGet
     */
    public function apiUserGet(User $user)
    {
        /**
         * build list of children / students if the requested user is a parent
         */
        $students = new Collection();
        if ($user->getRole()->isParent()) {
            if ($user->getExternalId()) {
                $enrolments = $this->externalUser->getEnrolledStudents($user->getExternalId());
                foreach ($enrolments as $child) {
                    try {
                        $students->add($this->userMapper->findByExternalId($child['RelatedID']));
                    } catch (MapperException $e) {
                        // noop
                    }
                }
            }
        }
        /**
         * build the groups list
         */
        $users_groups = $this->groupsHelper->getCurrentGroups($user);
        $apiGroups = new Collection($users_groups);
        $groupsArr = $apiGroups->map(new GroupArray());
        /**
         * build the whole thing
         */
        $output = (new ModelToArray())($user, $groupsArr, $students);
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }
}

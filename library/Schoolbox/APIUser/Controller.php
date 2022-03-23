<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Groups\GroupsHelper;
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
     * Controller constructor.
     *
     * @param UserAccessMapper       $userAccessMapper
     * @param GroupMapper            $groupMapper
     * @param FolderMapper           $folderMapper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param RerouteOverrideMapper  $reRouteOverrideMapper
     * @param UserMapper             $userMapper
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper,
        UserMapper $userMapper
    ) {
        $this->userMapper = $userMapper;
        $this->groupsHelper = new GroupsHelper(
            $groupMapper,
            $userAccessMapper,
            $folderMapper,
            $timetableColourMapper,
            $reRouteOverrideMapper,
        );
    }

    /**
     * Returns user object
     *
     * @param User $user
     * @api /api/user/{userId}
     * @link http://api.schoolbox.com.au/#operation/userGet
     */
    public function apiUserGet(User $user)
    {
        /**
         * build the internal groups list
         * we are not using User::getAccessList(), as that will also include SIS group folders,
         * that the user is unable to change membership of, or other details.
         */
        $users_groups = $this->groupsHelper->getCurrentGroups($user);
        $apiGroups = new Collection($users_groups);
        $groupsArr = $apiGroups->map(new GroupArray());

        //load parents and students / children for export.
        $user->loadParents()
             ->loadStudents();

        /**
         * build the whole thing
         */
        $output = (new User\Translator\ModelToArray())($user, $groupsArr);
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * Deletes a user object
     *
     * @param User $user
     * @throws \Schoolbox\Mapper\Exception
     * @link http://api.schoolbox.com.au/#operation/userDelete
     * @api  DELETE /user/{userId}
     */
    public function apiUserDelete(User $user)
    {
        $this->userMapper->delete($user);
        $this->getResponse()->status->setCode(204);
    }
}

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

    /**
     * Create a user
     *
     * @api POST /api/user
     * @link http://api.schoolbox.com.au/#operation/userPost
     */
    public function apiUserPost()
    {
        $data = json_decode(json_encode($this->request->content->get()), true);
        /**
         * check if ID is set in the post request, as the user should not be attempting to update a user at the POST method
         * that should be done at the PUT/PATCH method
         */
        $uid = $data['id'] ?? null;
        if (null !== $uid) {
            $problem = (new ApiProblem('Unable to update a user at this endpoint.'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the dont try to update a user at the add endpoint, that is for the PUT/PATCH route
        }

        $newuser = $this->userMapper->createModel($uid);

        /**
         * build the user Model object from the POST data
         */
        //set default fields
        $newuser->setEnabled(false);
        $newuser->setSuperuser(false);

        /** @var User $model */
        $model = (new User\Translator\ArrayToModel())($data, $newuser);

        $this->userMapper->save($model);

        //get hydrated user, or the campus/role/house wont have names, just ids...
        $user = $this->userMapper->findById($model->getId());
        $users_groups = $this->groupsHelper->getCurrentGroups($user);
        $apiGroups = new Collection($users_groups);
        $groupsArr = $apiGroups->map(new GroupArray());
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
}

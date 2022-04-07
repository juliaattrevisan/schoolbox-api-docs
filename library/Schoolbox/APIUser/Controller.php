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
use HouseMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\APIGroup\ModelToArray as GroupArray;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Timetable_ColourMapper;

class Controller extends Base
{
    private GroupsHelper $groupsHelper;
    protected UserMapper $userMapper;
    protected HouseMapper $houseMapper;
    protected CampusMapper $campusMapper;
    protected YearLevelMapper $yearLevelMapper;
    protected RoleMapper $roleMapper;

    /**
     * Controller constructor.
     *
     * @param UserAccessMapper       $userAccessMapper
     * @param GroupMapper            $groupMapper
     * @param FolderMapper           $folderMapper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param RerouteOverrideMapper  $reRouteOverrideMapper
     * @param UserMapper             $userMapper
     * @param HouseMapper            $houseMapper
     * @param CampusMapper           $campusMapper
     * @param YearLevelMapper        $yearLevelMapper
     * @param RoleMapper             $roleMapper
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper,
        UserMapper $userMapper,
        HouseMapper $houseMapper,
        CampusMapper $campusMapper,
        YearLevelMapper $yearLevelMapper,
        RoleMapper $roleMapper
    ) {
        $this->userMapper = $userMapper;
        $this->houseMapper = $houseMapper;
        $this->campusMapper = $campusMapper;
        $this->yearLevelMapper = $yearLevelMapper;
        $this->roleMapper = $roleMapper;
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
        if (null === $data) {
            throw new SchoolboxException("POST Body is empty");
        }
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

        if (!array_key_exists('username', $data)) {
            throw new SchoolboxException("username unable to be null");
        }
        if (!array_key_exists('firstName', $data)) {
            throw new SchoolboxException("firstName unable to be null");
        }
        if (!array_key_exists('altEmail', $data)) {
            throw new SchoolboxException("altEmail unable to be null");
        }
        if (!array_key_exists('campus', $data)) {
            throw new SchoolboxException("Campus unable to be null");
        }
        if (!array_key_exists('role', $data)) {
            throw new SchoolboxException("Role unable to be null");
        }

        $newUser = $this->userMapper->createModel($uid);

        /**
         * set sane defaults
         */
        $newUser->setEnabled(true)
            ->setSuperuser(false)
            ->setLocalEmail(false);

        /**
         * build the user Model object from the POST data
         */
        //set default fields

        $this->setUserFields($newUser, $data);
    }

    /**
     * updates all fields for a user
     *
     * @api PUT /api/user/{userId}
     * @link http://api.schoolbox.com.au/#operation/userPut
     */
    public function apiUserPut(User $user)
    {
        $data = json_decode(json_encode($this->request->content->get()), true);
        if (null === $data) {
            throw new SchoolboxException("PUT Body is empty");
        }

        $userModal = $this->userMapper->createModel($user->getId());

        /**
         * build the user Model object from the POST data
         */
        //set default fields
        $this->setUserFields($userModal, $data);
    }

    /**
     *  updates selected fields for a user
     *
     * @api PATCH /api/user/{userId}
     * @link http://api.schoolbox.com.au/#operation/userPATCH
     */
    public function apiUserPatch(User $user)
    {
        $data = json_decode(json_encode($this->request->content->get()), true);

        if (null === $data) {
            throw new SchoolboxException("PATCH Body is empty");
        }

        /**
         * build the user Model object from the POST data
         */
        //set default fields
        $this->setUserFields($user, $data);
    }

    /**
     * @param User $user
     * @param array $data
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function setUserFields(User $user, array $data): void
    {
        //check if what the user is attempting to edit is valid?
        if (array_key_exists('groups', $data)) {
            throw new SchoolboxException("groups field is unable to be updated at this endpoint");
        }
        if (array_key_exists('students', $data)) {
            throw new SchoolboxException("students field is unable to be updated at this endpoint");
        }
        if (array_key_exists('guardians', $data)) {
            throw new SchoolboxException("guardians field is unable to be updated at this endpoint");
        }
        if (array_key_exists('fullName', $data)) {
            throw new SchoolboxException("'fullName' is unable to be updated, please use title, firstName, lastname.");
        }
        if (array_key_exists('email', $data)) {
            //noop if email field with either be based on the username `isEmailDerived` or will be the `altEmail`
            throw new SchoolboxException("'email' is unable to be updated. 'email' field is based on isEmailDerived, or altEmail.");
        }
        if (array_key_exists('isDeleted', $data)) {
            //noop if email field with either be based on the username `isEmailDerived` or will be the `altEmail`
            throw new SchoolboxException("Unable to Delete a user from this endpoint, please use the DELETE method");
        }

        /**
         * build the db check data for the new model.
         */
        if (array_key_exists('yearLevel', $data)) {
            $yearLevel = $data['yearLevel']['id'] ?? null;
            if (null !== $yearLevel) {
                try {
                    $data['yearLevel'] = $this->yearLevelMapper->findById($yearLevel);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    throw new SchoolboxException("Year '$yearLevel' Not Found");
                }
            } else {
                $data['yearLevel'] = null;
            }
        }
        /**
         * campus creation
         */
        if (array_key_exists('campus', $data)) {
            $campus = $data['campus'];
            $campusCollection = (new GenericCollection(Campus::class));
            foreach ($campus as $campusEntry) {
                $campusId = $campusEntry['id'] ?? null;
                if (null !== $campusId) {
                    try {
                        $campusObj = $this->campusMapper->findById($campusId);
                    } catch (NotFound | Deleted $e) {
                        throw new SchoolboxException("Campus '$campusId' Not Found or has been Deleted");
                    }
                    $campusCollection->add($campusObj);
                } else {
                    throw new SchoolboxException("Campus '$campusId' Not Found");
                }
            }
            $data['campusCollection'] = $campusCollection;
            unset($data['campus']);
        }
        /**
         * get schoolHouse
         */
        if (array_key_exists('schoolHouse', $data)) {
            $schoolHouse = $data['schoolHouse']['id'] ?? null;
            if (null !== $schoolHouse) {
                try {
                    $house               = $this->houseMapper->findById($schoolHouse);
                    $data['schoolHouse'] = $house;
                } catch (NotFound | Deleted $e) {
                    throw new SchoolboxException("House '$schoolHouse' Not Found or has been Deleted");
                }
            }
        }
        /**
         * impersonatable users
         */
        if (array_key_exists('impersonableUser', $data)) {
            $impersonate = $data['impersonableUser'];
            $impersonateCollection = new GenericCollection(User::class);
            foreach ($impersonate as $userEntry) {
                $userId = $userEntry['id'] ?? null;
                if (null !== $userId) {
                    try {
                        $impUser = $this->userMapper->findById($userId);
                    } catch (NotFound | Deleted $e) {
                        throw new SchoolboxException("Impersonate User '$userId' Not Found or has been Deleted");
                    }
                    $impersonateCollection->add($impUser);
                } else {
                    throw new SchoolboxException("Impersonate User '$userId' Not Found or has been Deleted");
                }
            }
            $data['linkedUser'] = $impersonateCollection;
            unset($data['impersonableUser']);
        }

        /**
         * add role object
         */
        if (array_key_exists('role', $data)) {
            $roleId = $data['role']['id'] ?? null;
            if (null !== $roleId) {
                try {
                    $role = $this->roleMapper->findById($roleId);
                    $data['role'] = $role;
                } catch (NotFound | Deleted $e) {
                    throw new SchoolboxException("Role '$roleId' Not Found or has been Deleted");
                }
            } else {
                throw new SchoolboxException("Role '$roleId' Not Found or has been Deleted");
            }
        }

        /**
         * build the user Model object from the POST data
         */
        /** @var User $model */
        $model = (new User\Translator\ArrayToModel())($data, $user);

        try {
            $this->userMapper->save($model);
        } catch (\UnexpectedValueException $e) {
            throw new SchoolboxException($e->getMessage(), 400, $e);
        }

        //get hydrated user, or the campus/role/house wont have names, just ids...
        $updatedUser = $this->userMapper->findById($model->getId());
        $users_groups = $this->groupsHelper->getCurrentGroups($updatedUser);
        $apiGroups = new Collection($users_groups);
        $groupsArr = $apiGroups->map(new GroupArray());
        /**
         * build the whole thing
         */
        $output = (new User\Translator\ModelToArray())($updatedUser, $groupsArr);
        $this->response->content->set(
            $this->buildJsonOutput(
                $output
            )
        );
    }

    /**
     * Searches for a user based on a filter list
     * param: {search: search basic term} {filter: extra parameters for determining how to filter the search results}
     *
     * @api /api/user
     * @link http://api.schoolbox.com.au/#operation/userSearch
     */
    public function apiUserSearch()
    {
        $search = $this->request->query->get('search', '');
        $cursor = (int)$this->request->query->get('cursor', null);
        $limit = (int)$this->request->query->get('limit', 5);
        if ('' === $search) {
            $problem = (new ApiProblem('Search must not be blank'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }
        if (strlen($search) <= 2) {
            $problem = (new ApiProblem('Search term must be longer than 2 characters'))
                ->setStatus(401)
                ->asArray();
            $this->getResponse()->status->setCode(401);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early there is a malformed keyword for search
        }
        $filter = json_decode($this->request->query->get('filter', ''), true) ?? null;
        $userHelper = new Helper($this->userMapper);
        $output = [];
        $ajaxSearch = $userHelper->ajaxSearch($search, $filter, $limit, $cursor);
        $userIds = [];
        foreach ($ajaxSearch as $item) {
            foreach ($item as $label => $value) {
                if ('uid' === $label) {
                    $userIds[] = $value;
                }
            }
        }
        /**
         * now we have the user id's that match the search result, create the standard user array with hydration.
         */
        $users = $this->userMapper->findByColumn('id', $userIds);
        foreach ($users as $user) {
            $users_groups = $this->groupsHelper->getCurrentGroups($user);
            $apiGroups = new Collection($users_groups);
            $groupsArr = $apiGroups->map(new GroupArray());

            //load parents and students / children for export.
            $user->loadParents()
                 ->loadStudents();

            /**
             * build the whole thing
             */
            $output[] = (new User\Translator\ModelToArray())($user, $groupsArr);
        }
        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'data'  =>  $output,
                    'metadata'  => [
                        'count' => count($output),
                        'cursor' => end($output)['id'] ?? null
                    ]
                ]
            )
        );
    }
}

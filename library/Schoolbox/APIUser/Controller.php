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
use Schoolbox\Group\API\ModelToArray;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Group\GroupsHelper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Timetable_ColourMapper;
use UnexpectedValueException;

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
        $apiGroups = $this->groupsHelper->getCurrentGroups($user);
        $groupsArr = $apiGroups->map(new ModelToArray());

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
            throw new SchoolboxException("Request BODY has an issue, it may be empty or malformed");
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

        /**
         * check for the existence the required fields...
         */
        if (
            !isset($data['username'], $data['role'], $data['campus'], $data['firstName'])
            ||
            !is_array($data['role'])
            ||
            !is_array($data['campus'])
        ) {
            throw new SchoolboxException("POST request must include at least the following fields: username, role, campus, firstName");
        }

        $newUser = $this->userMapper->createModel($uid);

        /**
         * set sane defaults
         */
        $newUser->setEnabled(true)
            ->setSuperuser(false)
            ->setLocalEmail(false)
            ->setWebmail(false)
            ->setUseInternalMail(false)
            ->setEportfolioEnable(false);

        /**
         * build the user Model object from the POST data
         */
        //set default fields

        $this->setUserFields($newUser, $data);

        /**
         * set default response code to 201 (created)
         * if there are errors they will set their own code
         */
        $this->response->status->setCode(201);
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
            throw new SchoolboxException("Request BODY has an issue, it may be empty or malformed");
        }

        /**
         * check for the existence the required fields...
         */
        if (
            !isset($data['username'], $data['role'], $data['campus'], $data['firstName'])
            ||
            !is_array($data['role'])
            ||
            !is_array($data['campus'])
        ) {
            throw new SchoolboxException("PUT request must include at least the following fields: username, role, campus, firstName");
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
            throw new SchoolboxException("Request BODY has an issue, it may be empty or malformed");
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
            if (!is_array($campus)) {
                throw new SchoolboxException("Campus must be an array");
            }
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
            } else {
                $data['schoolHouse'] = null;
            }
        }
        /**
         * impersonatable users
         */
        if (array_key_exists('impersonableUser', $data)) {
            $impersonate = $data['impersonableUser'];
            if (!is_array($impersonate)) {
                throw new SchoolboxException("impersonableUser must be an array");
            }
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
        } catch (Exception | UnexpectedValueException $e) {
            throw new SchoolboxException($e->getMessage(), 400, $e);
        }

        //get hydrated user, or the campus/role/house wont have names, just ids...
        $updatedUser = $this->userMapper->findById($model->getId());
        $apiGroups = $this->groupsHelper->getCurrentGroups($updatedUser);
        $groupsArr = $apiGroups->map(new ModelToArray());
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
     * param: {search: search basic term (optional)} {filter: extra parameters for determining how to filter the search results}
     *
     * @api /api/user
     * @link http://api.schoolbox.com.au/#operation/userSearch
     */
    public function apiUserSearch()
    {
        $search = $this->request->query->get('search', '');
        $cursor = (int)$this->request->query->get('cursor', null);
        $limit = (int)$this->request->query->get('limit', 5);
        /**
         * check if filter is an array or string and use it like that
         */
        $filter = null;
        $getFilter = $this->request->query->get('filter', '');
        if (is_string($getFilter)) {
            $filter = json_decode($this->request->query->get('filter', ''), true) ?? null;
        } elseif (is_array($getFilter)) {
            $filter = $getFilter;
        }

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
            $apiGroups = $this->groupsHelper->getCurrentGroups($user);
            $groupsArr = $apiGroups->map(new ModelToArray());

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
                        'count' => $userHelper->ajaxSearchCount($search, $filter),
                        'cursor' => end($output)['id'] ?? null
                    ]
                ]
            )
        );
    }
}

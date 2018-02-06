<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\User;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Password\Service as PasswordService;
use Alaress\Redis;
use Alaress\Web\Controller\Base;
use HouseMapper;
use HouseResidentialMapper;
use Monolog\Logger;
use Schoolbox\Application\Exception\NotFound as ExceptionNotFound;
use Schoolbox\Campus;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Alert\RedisPublisher;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\User\Title\Mapper as UserTitleMapper;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User\Log\Mapper as UserLogMapper;
use Schoolbox\CMS\Log\Mapper as CMSLogMapper;
use Scrub;

/**
 * Controller for managing school users through the admin section.
 *
 * @package Schoolbox\Admin\Users
 */
class Controller extends Base
{
    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var UserTitleMapper
     */
    protected $userTitleMapper;

    /**
     * @var CampusMapper
     */
    protected $campusMapper;

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var HouseResidentialMapper
     */
    protected $houseResidentialMapper;

    /**
     * @var HouseMapper
     */
    protected $houseMapper;

    /**
     * @var UserLogMapper;
     */
    protected $userLogMapper;

    /**
     * @var CMSLogMapper;
     */
    protected $cmsLogMapper;

    /**
     * Controller constructor.
     * @param CMSLogMapper $cmsLogMapper
     * @param UserLogMapper $userLogMapper
     * @param FolderMapper $folderMapper
     * @param UserMapper $userMapper
     * @param ErrorMapper $errorMapper
     * @param UserAccessMapper $userAccessMapper
     * @param UserTitleMapper $userTitleMapper
     * @param CampusMapper $campusMapper
     * @param RoleMapper $roleMapper
     * @param HouseResidentialMapper $houseResidentialMapper
     * @param HouseMapper $houseMapper
     */
    public function __construct(
        CMSLogMapper $cmsLogMapper,
        UserLogMapper $userLogMapper,
        FolderMapper $folderMapper,
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        UserAccessMapper $userAccessMapper,
        UserTitleMapper $userTitleMapper,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseResidentialMapper $houseResidentialMapper,
        HouseMapper $houseMapper
    ) {
        $this->cmsLogMapper = $cmsLogMapper;
        $this->userLogMapper = $userLogMapper;
        $this->folderMapper = $folderMapper;
        $this->userMapper = $userMapper;
        $this->errorMapper = $errorMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->userTitleMapper = $userTitleMapper;
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->houseResidentialMapper = $houseResidentialMapper;
        $this->houseMapper = $houseMapper;
    }

    /**
     * Modify user screen
     * @param User $user
     */
    public function getModify($user)
    {
        // Check for non-existent user
        if ($user->getId() == NULL) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        if ($this->getConfigLoader()->get('external_enable') && $this->getConfigLoader()->get('external_type') == 'synergetic' && $user->getExternalId() > 0) {
            $externalUser = $this->di->get('externalUser');
            $fileSemester = $externalUser->getCurrentSemester($user->getExternalId());
            $fileYear = $externalUser->getCurrentYear($user->getExternalId());
        } else {
            $fileSemester = '';
            $fileYear = '';
        }

        $userPermissions = $this->userAccessMapper->findAllForUser($user);
        $groupPermissions = $userPermissions->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
                                            ->indexBy('serviceIdentifier');

        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data' => [
                'pageCurrent' => 'user',
                'campuses' => $this->campusMapper->findAll(),
                'roles' => $this->roleMapper->findAll(),
                'externalDatabase' => ucfirst($this->getConfigLoader()->get('external_type')),
                'residentialHouses' => $this->houseResidentialMapper->findAll(),
                'houses' => $this->houseMapper->findAll(),
                'titles' => $this->userTitleMapper->findAll(),
                'fileYear' => $fileYear,
                'fileSemester' => $fileSemester,
                'allGroups' => $this->folderMapper->searchByName('', true)->sortBy('name', 'asc'),
                'user' => $user,
                'userGroups' => $this->folderMapper->findForUser($user, false, UserAccess::ACCESS_READ),
                'userGroupPermissions' => $groupPermissions,
                'userPermissions' => $userPermissions,
                'userLog' => $this->userLogMapper->findByColumn('username', $user->getUsername(),[
                    'order' => ['date' => 'desc'],
                    'limit' => 10
                ]),
                'cmsLog' => $this->cmsLogMapper->findByColumn('user', $user->getId(), [
                    'order' => ['time' => 'desc'],
                    'limit' => 10
                ])
            ],
            'title' => 'Admin - Users'
        ]);
    }

    /**
     * Insert user screen
     */
    public function getInsert()
    {
        $user = $this->userMapper->createModel()
            ->setDisplayGradeGraph($this->getConfigLoader()->get('display_grade_graph'));
        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data' => [
                'pageCurrent' => 'user',
                'user' => $user,
                'campuses' => $this->campusMapper->findAll(),
                'roles' => $this->roleMapper->findAll(),
                'externalDatabase' => ucfirst($this->getConfigLoader()->get('external_type')),
                'residentialHouses' => $this->houseResidentialMapper->findAll(),
                'houses' => $this->houseMapper->findAll(),
                'titles' => $this->userTitleMapper->findAll(),
            ],
            'title' => 'Admin - Users'
        ]);
    }

    /**
     * Add group membership
     * @param \Schoolbox\User $user
     */
    public function postInsertGroup($user)
    {
        $post = $this->getRequest()->post;

        try {
            $userAccess = new UserAccess();
            $userAccess->setService(UserAccess::SERVICE_HOMEPAGE);
            $userAccess->setUser($user);
            $userAccess->setAccessLevel($post['type']);
            $userAccess->setServiceIdentifier($post['sid']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->redirect->afterPost("/adminv2/user/{$user->getId()}/modify#groups");
    }

    /**
     * Add service permission
     * @param \Schoolbox\User $user
     */
    public function postInsertPermission($user)
    {
        $post = $this->getRequest()->post;

        try {
            $userAccess = new UserAccess();
            $userAccess->setService($post['service']);
            $userAccess->setUser($user);
            $userAccess->setAccessLevel($post['type']);
            $userAccess->setServiceIdentifier(empty($post['sid']) ? 0 : $post['sid']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->redirect->afterPost("/adminv2/user/{$user->getId()}/modify#permissions");
    }

    /**
     * Add linked user
     * @param \Schoolbox\User $user
     */
    public function postInsertLinked($user)
    {
        $post = $this->getRequest()->post;

        try {
            foreach ($post['linkUser'] as $userId) {
                try {
                    $linkedUser = $this->userMapper->findById($userId);
                    $user->getLinkedUser()->add($linkedUser);
                } catch (MapperException $e) {
                }
            }

            $this->userMapper->save($user);
        } catch (MapperException $e) {
        }

        $this->getResponse()->redirect->afterPost("/adminv2/user/{$user->getId()}/modify#linked");
    }

    /**
     * Remove a group from a users account
     * @param User $user
     * @param \Schoolbox\Resource\Folder\Model $folder
     */
    public function postDeleteGroup($user, $folder)
    {
        $this->userAccessMapper->deleteByColumnList([
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'sid' => $folder->getId(),
            'uid' => $user->getId()
        ]);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => true
        ]);
    }

    /**
     * Remove a service permission from a users account
     * @param User $user
     * @param int $sid
     * @param int $service
     */
    public function postDeletePermission($user, $sid, $service)
    {
        $this->userAccessMapper->deleteByColumnList([
            'service' => $service,
            'sid' => $sid,
            'uid' => $user->getId()
        ]);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => true
        ]);
    }

    /**
     * Remove a linked user from a persons account
     * @param User $user Account to remove link from
     * @param User $linked Linked user to remove
     */
    public function postDeleteLinked($user, $linked)
    {
        $linkedUserList = new Collection();

        foreach ($user->getLinkedUser() as $linkedUser) {
            if ($linkedUser->getId() != $linked->getId()) {
                $linkedUserList->add($linkedUser);
            }
        }

        $user->setLinkedUser($linkedUserList);

        $this->userMapper->save($user);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => true
        ]);
    }


    /**
     * Modify user details
     */
    public function postDetails()
    {
        $post = $this->getRequest()->post;

        $passwordService = new PasswordService();

        if (is_numeric($post['userID']) && $post['modusername']) {

            // Modify existing user

            try {
                $user = $this->userMapper->findById($post['userID']);
            } catch (MapperException $e) {
                // User could not be found
                throw new ExceptionNotFound();
            }

            // Username check - if any other user has this username, abort
            foreach ($this->userMapper->findByColumn('username', $post['modusername']) as $userWithUsername) {
                if ($userWithUsername->getId() != $user->getId()) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_USERNAME_EXISTS);
                }
            }
        } else {

            if ($post['modusername']) {
                // Username check - if any other user has this username, abort
                if ($this->userMapper->countByColumn('username', $post['modusername']) > 0) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_USERNAME_EXISTS);
                }
            }
        }

        if (!isset($user)) {

            // Creating a new user

            $user = new User();

            // New users get enabled automatically

            $user->setEnabled(true);

            // Set password

            if ($post['modpassword'] == '') {
                $user->setRetrievablePassword(null)
                     ->setPassword(null);
            } else {
                $user->setRetrievablePassword($post['modpassword'])
                     ->setPassword($passwordService->hash($post['modpassword']));
            }

        } else {

            // Updating an existing user, only password only if the user edited the textbox

            if ($post['modpassword'] != 'donotchange') {
                $user->setRetrievablePassword($post['modpassword'])
                     ->setPassword($passwordService->hash($post['modpassword']));
            }

            $user->setEnabled(isset($post['account_enabled']));
        }

        if(!empty($post['house'])) {
            $house = $this->houseMapper->createModel($post['house']);
        }
        else {
            $house = null;
        }

        if(!empty($post['residential_house'])) {
            $resHouse = $this->houseResidentialMapper->createModel($post['residential_house']);
        }
        else {
            $resHouse = null;
        }

        $user->setTitle($post['title']);
        $user->setFirstname($post['firstname']);
        $user->setLastname($post['lastname']);
        $user->setExternalId($post['database_id'] == '' ? null : $post['database_id']);
        $user->getRole()->setId($post['group']);
        $user->setPositionTitle($post['position_title']);
        $user->setAltEmail(trim($post['alt_email']) == '' ? null : $post['alt_email']);
        $user->setYear($post['year'] == '' ? null : $post['year']);
        $user->setSchoolHouse($house);
        $user->setResidentialHouse($resHouse);
        $user->setEportfolioEnable(!empty($post['eportfolio']));
        $user->setHideContacts(!empty($post['hide_contacts']));
        $user->setHideTimetable(!empty($post['hide_timetable']));
        $user->setLocalEmail(!empty($post['local_email']));
        $user->setUseInternalMail(!empty($post['mail_handler']));
        $user->setWebmail(!empty($post['webmail']));
        $user->setDisplayGradeGraph(!empty($post['display_grade_graph']));
        $user->setSuperuser(isset($post['superuser']));
        $user->setUsername($post['modusername']);


        if (is_array($post['campus'])) {

            $user->setCampusCollection(new GenericCollection(Campus::class));

            foreach ($post['campus'] as $campusId) {
                $campus = new Campus();
                $campus->setId($campusId);
                $user->getCampusCollection()->add($campus);
            }
        }

        // Save the user

        $this->userMapper->save($user);

        // Redirect back to modify page

        $this->getResponse()->redirect->afterPost("/adminv2/user/{$user->getId()}/modify");
    }

    /**
     * Delete a user
     * @param User $user
     */
    public function postDeleteUser($user)
    {
        $this->userMapper->delete($user);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => true
        ]);
    }

    /**
     * Send instant alert to user
     * @param \Schoolbox\User $user
     */
    public function postAlert($user)
    {
        $post = $this->getRequest()->post;

        $userId = $user->getId();

        $bob = new NotificationInstanceBuilder();
        $bob->setTitle(Scrub::text($post['body']))
            ->setSeverity(Logger::INFO)
            ->setNamespace('ALERT')
            ->setHref('#')
            ->setIcon('/images/icon.php?icon=bolt&size=64')
            ->addTopic("notify.user.$userId")
            ->putMeta('ttl', 30)
            ->putMeta('title', 'Message from ' . $this->getUser()->getFullName());

        (new RedisPublisher(Redis::getClientInstance(), INSTANCE_ID))->alertUser($userId, $bob->build());

        // Redirect back to modify page

        $this->getResponse()->redirect->afterPost("/adminv2/user/{$userId}/modify");
    }

    /**
     * Update a groups access level
     * @param \Schoolbox\User $user
     * @param \Schoolbox\Resource\Folder\Model $folder
     */
    public function postUpdateGroup($user, $folder)
    {
        $post = $this->getRequest()->post;

        try {
            // If assigning a group admin, demote any existing group admins

            if ($post['type'] == FULL_ACCESS) {
                $admins = $this->userAccessMapper->findByColumnList([
                    'uid' => $user->getId(),
                    'sid' => $folder->getId(),
                    'service' => UserAccess::SERVICE_HOMEPAGE,
                    'type' => FULL_ACCESS
                ]);

                /** @var UserAccess $admin */
                foreach ($admins as $admin) {
                    $admin->setAccessLevel(WRITE_ACCESS);
                    $this->userAccessMapper->save($admin);
                }
            }

            /** @var UserAccess $userAccess */
            $userAccess = $this->userAccessMapper->findOneByColumnList([
                'uid' => $user->getId(),
                'sid' => $folder->getId(),
                'service' => UserAccess::SERVICE_HOMEPAGE
            ]);
            $userAccess->setAccessLevel($post['type']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => true
        ]);
    }

    /**
     * Update a service permission level
     * @param \Schoolbox\User $user
     */
    public function postUpdateService($user)
    {
        $post = $this->getRequest()->post;

        try {
            $userAccess = $this->userAccessMapper->findOneByColumnList([
                'uid' => $user->getId(),
                'sid' => $post['sid'],
                'service' => $post['service']
            ]);
            $userAccess->setAccessLevel($post['type']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => true
        ]);
    }
}

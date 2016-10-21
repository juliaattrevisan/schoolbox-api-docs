<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\User;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Password\Service as PasswordService;
use Alaress\Web\Controller\Base;
use Group;
use HouseMapper;
use HouseResidentialMapper;
use Monolog\Logger;
use Schoolbox\Campus;
use Schoolbox\CampusCollection;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\User\Title\Mapper as UserTitleMapper;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User\Log\Mapper as UserLogMapper;
use Schoolbox\CMS\Log\Mapper as CMSLogMapper;
use Scrub;
use Session;
use User_Logger_AssumeUser;

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
     * @var \HouseResidentialMapper
     */
    protected $houseResidentialMapper;

    /**
     * @var \HouseMapper
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
        if (\Cfg::get('external_enable') && \Cfg::get('external_type') == 'synergetic' && $user->getExternalId() > 0) {
            $externalUser = new \External_Synergetic_User();
            $fileSemester = $externalUser->getCurrentSemester($user->getExternalId());
            $fileYear = $externalUser->getCurrentYear($user->getExternalId());
        } else {
            $fileSemester = '';
            $fileYear = '';
        }

        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data' => [
                'pageCurrent' => user,
                'campuses' => $this->campusMapper->findAll(),
                'roles' => $this->roleMapper->findAll(),
                'externalDatabase' => ucfirst(\Cfg::get('external_type')),
                'residentialHouses' => $this->houseResidentialMapper->findAll(),
                'houses' => $this->houseMapper->findAll(),
                'titles' => $this->userTitleMapper->findAll(),
                'fileYear' => $fileYear,
                'fileSemester' => $fileSemester,
                'folders' => $this->folderMapper->findAll()->sortBy('name', 'asc'),
                'user' => $user,
                'currentUser' => $this->getUser(),
                'userAccess' => $this->userAccessMapper->findAllGroupsForUser($user),
                'userPermissions' => $this->userAccessMapper->findAllForUser($user),
                'userLog' => $this->userLogMapper->findByColumn('username', $user->getUsername())->sortBy('date', 'desc')->limit(0, 10),
                'cmsLog' => $this->cmsLogMapper->findByUser($user, 10)->sortBy('time', 'desc')
            ],
            'title' => 'Admin - Users'
        ]);
    }

    /**
     * Insert user screen
     * @param User $user
     */
    public function getInsert($user)
    {
        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data' => [
                'pageCurrent' => 'user',
                'campuses' => $this->campusMapper->findAll(),
                'roles' => $this->roleMapper->findAll(),
                'externalDatabase' => ucfirst(\Cfg::get('external_type')),
                'residentialHouses' => $this->houseResidentialMapper->findAll(),
                'houses' => $this->houseMapper->findAll(),
                'titles' => $this->userTitleMapper->findAll(),
            ],
            'title' => 'Admin - Users'
        ]);
    }

    /**
     * Add group membership
     */
    public function postInsertGroup()
    {
        $post = $this->getRequest()->post;

        try {
            $userAccess = new UserAccess();
            $userAccess->setService(UserAccess::SERVICE_HOMEPAGE);
            $userAccess->setUser($this->userMapper->findById($post['uid']));
            $userAccess->setAccessLevel($post['type']);
            $userAccess->setServiceIdentifier($post['sid']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
        }

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$post['uid']}#groups");
    }

    /**
     * Add service permission
     */
    public function postInsertPermission()
    {
        $post = $this->getRequest()->post;

        try {
            $userAccess = new UserAccess();
            $userAccess->setService($post['service']);
            $userAccess->setUser($this->userMapper->findById($post['uid']));
            $userAccess->setAccessLevel($post['type']);
            $userAccess->setServiceIdentifier(empty($post['sid']) ? 0 : $post['sid']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
        }

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$post['uid']}#permissions");
    }

    /**
     * Add linked user
     */
    public function postInsertLinked()
    {
        $post = $this->getRequest()->post;

        try {
            $originalUser = $this->userMapper->findById($post['userID']);

            foreach ($post['linkUser'] as $userId) {
                try {
                    $linkedUser = $this->userMapper->findById($userId);
                    $originalUser->getLinkedUser()->add($linkedUser);
                } catch (MapperException $e) {
                }
            }

            $this->userMapper->save($originalUser);
        } catch (MapperException $e) {
        }

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$post['userID']}#linked");
    }

    /**
     * Remove a group from a users account
     * @param User $user
     * @param \Schoolbox\Resource\Folder\Model $folder
     */
    public function getDeleteGroup($user, $folder)
    {
        $this->userAccessMapper->deleteByColumnList([
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'sid' => $folder->getId(),
            'uid' => $user->getId()
        ]);

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$user->getId()}#groups");
    }

    /**
     * Remove a service permission from a users account
     * @param User $user
     * @param int $sid
     * @param int $service
     */
    public function getDeletePermission($user, $sid, $service)
    {
        $this->userAccessMapper->deleteByColumnList([
            'service' => $service,
            'sid' => $sid,
            'uid' => $user->getId()
        ]);

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$user->getId()}#permissions");
    }

    /**
     * Remove a linked user from a persons account
     * @param User $user Account to remove link from
     * @param User $linked Linked user to remove
     */
    public function getDeleteLinked($user, $linked)
    {
        $linkedUserList = new Collection();

        foreach ($user->getLinkedUser() as $linkedUser) {
            if ($linkedUser->getId() != $linked->getId()) {
                $linkedUserList->add($linkedUser);
            }
        }

        $user->setLinkedUser($linkedUserList);

        $this->userMapper->save($user);

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$user->getId()}#linked");
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
                error(31);
                exit;
            }

            // Username check - if any other user has this username, abort
            foreach ($this->userMapper->findByColumn('username', $post['modusername']) as $userWithUsername) {
                if ($userWithUsername->getId() != $user->getId()) {
                    error(138);
                    exit;
                }
            }
        } else {

            if ($post['modusername']) {
                // Username check - if any other user has this username, abort
                if ($this->userMapper->countByColumn('username', $post['modusername']) > 0) {
                    error(138);
                    exit;
                }
            }
        }

        if (!isset($user)) {

            // Creating a new user

            $user = new \Schoolbox\User();

            // Set password

            $user->setPassword($post['modpassword'] == '' ? null : $passwordService->hash($post['modpassword']));

        } else {

            // Updating an existing user, only password only if the user edited the textbox

            if ($post['modpassword'] != 'donotchange') {
                $user->setPassword($passwordService->hash($post['modpassword']));
            }
        }

        $user->setTitle($post['title']);
        $user->setFirstname($post['firstname']);
        $user->setLastname($post['lastname']);
        $user->setStaffProfileComment($post['staff_profile_comment']);
        $user->setExternalId($post['database_id'] == '' ? null : $post['database_id']);
        $user->getRole()->setId($post['group']);
        $user->setAltEmail($post['alt_email']);
        $user->setYear($post['year'] == '' ? null : $post['year']);
        $user->getSchoolHouse()->setId($post['house']);
        $user->getResidentialHouse()->setId($post['residential_house']);
        $user->setEportfolioEnable(!empty($post['eportfolio']));
        $user->setHideContacts(!empty($post['hide_contacts']));
        $user->setHideTimetable(!empty($post['hide_timetable']));
        $user->setLocalEmail(!empty($post['local_email']));
        $user->setUseInternalMail(!empty($post['mail_handler']));
        $user->setWebmail(!empty($post['webmail']));
        $user->setSuperuser(isset($post['superuser']));
        $user->setUsername($post['modusername']);
        $user->setEnabled(isset($post['account_enabled']));

        if (is_array($post['campus']) && count($post['campus']) || $post['defaultCampus']) {
            foreach ($post['campus'] as $campusId) {
                $campus = new Campus();
                $campus->setId($campusId);
                if ($campusId == $post['defaultCampus']) {
                    $user->setDefaultCampus($campus);
                }
                $user->getCampusCollection()->add($campus);
            }
        }

        // Save the user

        $this->userMapper->save($user);

        // Redirect back to modify page

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$user->getId()}");
    }

    /**
     * Delete a user
     * @param User $user
     */
    public function getDeleteUser($user)
    {
        $this->userMapper->delete($user);
        $this->getResponse()->redirect->afterPost("/adminv2/user");
    }

    /**
     * Send instant alert to user
     */
    public function postAlert()
    {
        $post = $this->getRequest()->post;

        $userId = (int)$post['user'];

        $bob = new NotificationInstanceBuilder();
        $bob->setTitle(Scrub::text($post['body']))
            ->setSeverity(Logger::INFO)
            ->setNamespace('ALERT')
            ->setHref('#')
            ->setIcon('/images/icon.php?icon=bolt&size=64')
            ->addTopic("notify.user.$userId")
            ->putMeta('ttl', 30)
            ->putMeta('title', 'Message from ' . $this->getUser()->getFullName());

        $n = Notifactory::create("instant.user.$userId.alert", ConfigMapper::getInstance());
        $n->log(Logger::INFO, json_encode($bob->build()->jsonSerialize()));

        // Redirect back to modify page

        $this->getResponse()->redirect->afterPost("/adminv2/user/modify/{$userId}");
    }

    /**
     * Impersonate user
     */
    public function postImpersonate()
    {
        $post = $this->getRequest()->post;

        $oldSession = $_SESSION;

        $sessionHandler = new Session();

        if ($sessionHandler->create($post['login'])) {
            $this->getSession()->set('previous', $oldSession);
            $logger = new User_Logger_AssumeUser($oldSession['user'], $_SESSION['user']);
            $logger->notify();
            $this->getResponse()->redirect->afterPost("/");
        } else {
            $this->getResponse()->redirect->afterPost("/adminv2/user");
        }
    }

    /**
     * Update a groups access level
     */
    public function postUpdateGroup()
    {
        $post = $this->getRequest()->post;

        try {
            // If assigning a group admin, demote any existing group admins

            if ($post['type'] == FULL_ACCESS) {
                $admins = $this->userAccessMapper->findByColumnList([
                    'uid' => $post['uid'],
                    'sid' => $post['sid'],
                    'service' => $post['service'],
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
                'uid' => $post['uid'],
                'sid' => $post['sid'],
                'service' => $post['service']
            ]);
            $userAccess->setAccessLevel($post['type']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
        }
    }

    /**
     * Update a service permission level
     */
    public function postUpdateService()
    {
        $post = $this->getRequest()->post;

        try {
            $userAccess = $this->userAccessMapper->findOneByColumnList([
                'uid' => $post['uid'],
                'sid' => $post['sid'],
                'service' => $post['service']
            ]);
            $userAccess->setAccessLevel($post['type']);
            $this->userAccessMapper->save($userAccess);
        } catch (MapperException $e) {
        }
    }
}

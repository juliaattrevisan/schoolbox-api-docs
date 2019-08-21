<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\User;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Password\Service as PasswordService;
use Alaress\Redis;
use Alaress\Web\Controller\Base;
use HouseMapper;
use HouseResidentialMapper;
use Monolog\Logger;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\IStorage;
use Scrub;
use UnexpectedValueException;
use Schoolbox\Application\Exception\NotFound as ExceptionNotFound;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\CMS\Log\Mapper as CMSLogMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Alert\RedisPublisher;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\User\Log\Mapper as UserLogMapper;
use Schoolbox\User\Title\Mapper as UserTitleMapper;

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
     * These identifier consts determine which dropdown to use when choosing the Service Identifier
     * There are currently three options: campus, residentialHouse, none
     * Also represent those as an iterable
     */
    private const IDENTIFIER_NONE = 'none';
    private const IDENTIFIER_CAMPUS = 'campus';
    private const IDENTIFIER_RESIDENTIAL_HOUSE = 'residentialHouse';

    private const IDENTIFIERS = [
        'none' => self::IDENTIFIER_NONE,
        'campus' => self::IDENTIFIER_CAMPUS,
        'residentialHouse' => self::IDENTIFIER_RESIDENTIAL_HOUSE
    ];

    /**
     * Access Levels
     */
    private const ACCESS_LEVELS = [
        'read' => UserAccess::ACCESS_READ,
        'write' => UserAccess::ACCESS_WRITE,
        'moderate' => UserAccess::ACCESS_FULL
    ];

    /**
     * A mapping of services and what can appear in the Permissions section
     *
     * @var array
     */
    private const SERVICE_MAPPING = [

        /* For some historical reason, SERVICE_HOMEPAGE is no longer shown.
         */
        UserAccess::SERVICE_CALENDAR => [

            /* The text that appears in the dropdown list
             */
            'name' => 'Calendar',

            /* The service identifier options to use: campus dropdown, house dropdown, nothing (none)
             */
            'identifier' => self::IDENTIFIER_CAMPUS,

            /* Access levels that you can choose; some services are Moderate-Only, but this way you can specify
             * the available access levels more granularly e.g. write-only, if you ever needed that
             */
            'availability' => [
                'read' => true,
                'write' => true,
                'moderate' => true
            ]
        ],
        UserAccess::SERVICE_NEWS => [
            'name' => 'News',
            'identifier' => self::IDENTIFIER_NONE,
            'availability' => [
                'read' => false,
                'write' => true,
                'moderate' => true
            ]
        ],
        UserAccess::SERVICE_CMS => [
            'name' => 'CMS',
            'identifier' => self::IDENTIFIER_NONE,
            'availability' => [
                'read' => true,
                'write' => true,
                'moderate' => true
            ]
        ],
        UserAccess::SERVICE_HOUSEMASTER => [
            'name' => 'Housemaster',
            'identifier' => self::IDENTIFIER_RESIDENTIAL_HOUSE,
            'availability' => [
                'read' => false,
                'write' => false,
                'moderate' => true
            ]
        ],
        UserAccess::SERVICE_RESOURCE_BOOKING => [
            'name' => 'Resource Booking',
            'identifier' => self::IDENTIFIER_NONE,
            'availability' => [
                'read' => false,
                'write' => false,
                'moderate' => true
            ]
        ],
        UserAccess::SERVICE_CURRICULUM => [
            'name' => 'Curriculum',
            'identifier' => self::IDENTIFIER_NONE,
            'availability' => [
                'read' => false,
                'write' => true,
                'moderate' => false
            ]
        ],
        UserAccess::SERVICE_PASTORAL => [
            'name' => 'Pastoral Moderator',
            'identifier' => self::IDENTIFIER_NONE,
            'availability' => [
                'read' => true,
                'write' => true,
                'moderate' => true
            ]
        ],
        UserAccess::SERVICE_SPORTS_ADMINISTRATOR => [
            'name' => 'Fixtures',
            'identifier' => self::IDENTIFIER_NONE,
            'availability' => [
                'read' => false,
                'write' => false,
                'moderate' => true
            ]
        ],
    ];

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
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    public function getModify($user)
    {
        // Check for non-existent user
        if ($user->getId() === null) {
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

        $allPermissions   = $this->userAccessMapper->findAllForUser($user);

        $groupPermissions = $allPermissions->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
                                            ->indexBy('serviceIdentifier');

        /* Don't need the HOMEPAGE service; it's never used
         */
        $userPermissions  = $allPermissions->filter(function (UserAccess $access) {
            return UserAccess::SERVICE_HOMEPAGE !== $access->getService();
        });

        /* Attachzone for profile picture
         * If Load From SIS is on, then you cannot change the picture manually (automatically obtained)
         */
        $portraitZone = null;

        if (!$this->getConfigLoader()->get('photo_sis_import')) {
            $portraitZone = (new Zone('portrait'))
                ->setMulti(false)
                ->setAttachmentsLabel('')
                ->setAttachPrompt('add a portrait image')
                ->setMimeFilter('image/*')
                ->attachFiles([$user->getPortrait()])->getTwigContext();
        }


        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data' => [
                'serviceMapping' => self::SERVICE_MAPPING,
                'identifiers' => self::IDENTIFIERS,
                'accessLevel' => self::ACCESS_LEVELS,

                'pageCurrent' => 'user',
                'method' => 'Update',
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
                ]),
                'portraitZone' => $portraitZone
            ],
            'title' => 'Admin - Users'
        ]);
    }


    /**
     * Insert user screen
     * @throws UnexpectedValueException
     * @throws MapperException
     */
    public function getInsert()
    {
        $user = $this->userMapper->createModel()
            ->setDisplayGradeGraph($this->getConfigLoader()->get('display_grade_graph'));

        /* Attachzone for profile picture
         */
        $portraitZone = (new Zone('portrait'))
            ->setMulti(false)
            ->setAttachmentsLabel('')
            ->setAttachPrompt('add a portrait image');

        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data' => [
                'pageCurrent' => 'user',
                'method'    => 'Create',
                'user' => $user,
                'campuses' => $this->campusMapper->findAll(),
                'roles' => $this->roleMapper->findAll(),
                'externalDatabase' => ucfirst($this->getConfigLoader()->get('external_type')),
                'residentialHouses' => $this->houseResidentialMapper->findAll(),
                'houses' => $this->houseMapper->findAll(),
                'titles' => $this->userTitleMapper->findAll(),
                'portraitZone' => $portraitZone->getTwigContext()
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
        $user->setAltEmail(trim($post['alt_email']) == '' ? null : trim($post['alt_email']));
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


        /* If the config allows you to post images yourself, then
         * deal with the files you threw up into the attachzone
         */
        if (!$this->getConfigLoader()->get('photo_sis_import')) {
            $factory = new CollectorFactory();

            $attachments = [];

            /* Remove bad data SA-8244
             */
            if (\is_array($this->getRequest()->post['attachment'])) {
                $attachments = array_filter($this->getRequest()->post['attachment']);
            }

            /* If you didn't provide a picture, empty it
             */
            $user->setPortrait(null);

            if (\count($attachments)) {

                // Validate attachment list can actually be built
                try {
                    /** @var IStorage[] $attachments */
                    $attachments = $factory->build($this->getRequest()->post['attachment'])->getAll();
                } catch (\InvalidArgumentException $e) {

                    /* Bad data; noop
                     */
                }

                foreach ($attachments as $portrait) {
                    $user->setPortrait($portrait);
                }
            }
        }

        // Save the user
        try {
            $this->userMapper->save($user);
        } catch (UnexpectedValueException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PROCESSING, null, $e->getMessage());
        }

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

        if (trim($post['body']) === '') {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $bob = new NotificationInstanceBuilder();
        $bob->setTitle(Scrub::text($post['body']))
            ->setSeverity(Logger::INFO)
            ->setNamespace('ALERT')
            ->setHref('#')
            ->setIcon('/images/icon.php?size=64&icon='.NSMapper::NS_MESSAGE)
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

            if ($post['type'] == UserAccess::ACCESS_FULL) {
                $admins = $this->userAccessMapper->findByColumnList([
                    'uid' => $user->getId(),
                    'sid' => $folder->getId(),
                    'service' => UserAccess::SERVICE_HOMEPAGE,
                    'type' => UserAccess::ACCESS_FULL
                ]);

                /** @var UserAccess $admin */
                foreach ($admins as $admin) {
                    $admin->setAccessLevel(UserAccess::ACCESS_WRITE);
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

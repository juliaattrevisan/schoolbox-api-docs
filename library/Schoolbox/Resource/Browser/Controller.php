<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;

use Alaress\Web\Controller\Base as BaseController;

use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Config;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends BaseController
{
    /** @var Resource\Folder\Mapper */
    protected $folderMapper;

    /** @var Resource\FileMapper */
    protected $fileMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var RoleAccessMapper */
    protected $roleAccessMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var User */
    protected $sessionUser;

    /** @var Config\Loader */
    protected $conf;

    /** @var TaskMapper */
    protected $taskMapper;


    function __construct()
    {
        // TODO inject deps
        $this->folderMapper     = Resource\Folder\Mapper::getInstance();
        $this->fileMapper       = Resource\FileMapper::getInstance();
        $this->userAccessMapper = UserAccessMapper::getInstance();
        $this->roleAccessMapper = RoleAccessMapper::getInstance();
        $this->userMapper       = UserMapper::getInstance();
        $this->conf             = new Config\Loader();
        $this->taskMapper       = TaskMapper::getInstance();
    }


    /**
     * @param int $folderId
     * @return array
     */
    protected function getFolderData($folderId)
    {
        // TODO inject deps
        $this->sessionUser = $_SESSION['user'];

        $folder = $this->folderMapper->findById($folderId);
        $admins = $this->userAccessMapper->findAdminsInCategory($folder);
        $roles  = $this->roleAccessMapper->findAllForFolder($folder);

        if ($this->sessionUser->isSuperuser()) {
            $access = UserAccess::ACCESS_FULL;

        } else {
            try {
                $access = $this->userAccessMapper->findOneByColumnList([
                    'uid'     => $this->sessionUser->getId(),
                    'service' => UserAccess::SERVICE_HOMEPAGE,
                    'sid'     => $folderId
                ])->getAccessLevel();

            } catch (\Schoolbox\Mapper\Exception $e) {
                // no accesseses found...you get nothin'
                return [
                    'access' => UserAccess::ACCESS_DENY
                ];
            }
        }

        if ($admins->count()) {
            $admin = $this->userMapper->findById($admins->first()->getId());
        } else {
            $admin = new User();
            $admin->setFirstname('Nobody');
        }

        return [
            'folder' => $folder,
            'admin'  => $admin,
            'roles'  => $roles,
            'access' => $access,
            'user'   => $this->sessionUser,
            'conf'   => $this->conf
        ];

    }


    /**
     * Displays 3-panel browser
     *
     * @api GET /resources(/:folderId)
     * @level 150
     *
     * @param int $folderId
     */
    public function browse($folderId = 0)
    {
        // TODO inject deps
        $this->sessionUser = $_SESSION['user'];

        $tree = new Resource\Folder\TreeBuilder(
            $this->folderMapper,
            $this->taskMapper,
            $this->userAccessMapper,
            $this->roleAccessMapper,
            $this->sessionUser
        );

        $data = $this->getFolderData($folderId);

        // add files to that
        $data['files']    = $this->fileMapper->findByFolderId($folderId)->getAll();
        $data['treedata'] = json_encode($tree->buildPartialTree($folderId ?: '#'));

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/browse.twig',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/:folderId/children
     * @level 150
     *
     * @param int $folderId
     */
    public function listResources($folderId)
    {
        $data = ['files' => $this->fileMapper->findByFolderId($folderId)->getAll()];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/listResources.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/:folderId/info
     * @level 150
     *
     * @param int $folderId
     */
    public function showFolderInfo($folderId)
    {
        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFolderInfo.twig',
            'format'   => 'nochrome',
            'data'     => $this->getFolderData($folderId),
        ]);
    }


    /**
     * @api GET /resources/file/:fileId/info
     * @param int $fileId
     */
    public function showFileInfo($fileId)
    {
        // TODO inject deps
        $this->sessionUser = $_SESSION['user'];


        /** @var Resource\File $file */
        $file = $this->fileMapper->findById($fileId);

        $data = [ 'file' => $file ];

        if ($this->sessionUser->isSuperuser()) {
            $data['access'] = UserAccess::ACCESS_FULL;

        } else {
            try {
                $data['access'] = $this->userAccessMapper->findOneByColumnList([
                    'uid'     => $this->sessionUser->getId(),
                    'service' => UserAccess::SERVICE_HOMEPAGE,
                    'sid'     => $file->getFolder()->getId()
                ])->getAccessLevel();

            } catch (\Schoolbox\Mapper\Exception $e) {
                // no accesseses found...you get nothin'
                $data = [
                    'access' => UserAccess::ACCESS_DENY
                ];
            }
        }
        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }
} 
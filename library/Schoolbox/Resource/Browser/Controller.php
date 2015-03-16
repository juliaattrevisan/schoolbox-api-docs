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

        try {
            $folder = $this->folderMapper->findById($folderId);

        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            // Deleted: load the parent
            $folder = $e->getDeletedObject()->getParent();
        }
        catch (\Schoolbox\Mapper\Exception $e) {
            $folder = $this->folderMapper->createModel();
            $folder->setId(0);
        }

        $admins = $this->userAccessMapper->findAdminsInCategory($folder);
        $roles  = $this->roleAccessMapper->findAllForFolder($folder);

        // TODO hydrate roles for these users
        $folder->loadTeachers();

        try {
            $access = $this->userAccessMapper
                ->getAccess($this->sessionUser, $folderId)
                ->getAccessLevel();

        } catch (\Schoolbox\Mapper\Exception $e) {
            // no accesseses found...you get nothin'
            $access = null;
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
        $data['treedata'] = json_encode($tree->buildPartialTree($folderId));

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
     * @level 150
     *
     * @param int $fileId
     */
    public function showFileInfo($fileId)
    {
        // TODO inject deps
        $this->sessionUser = $_SESSION['user'];


        /** @var Resource\File $file */
        $file = $this->fileMapper->findById($fileId);
        // urgh...
        $file->setOwner($this->userMapper->findById($file->getOwner()->getId()));

        $data = [ 'file' => $file ];

        try {
            $data['access'] = $this->userAccessMapper
                                   ->getAccess($this->sessionUser, $file->getFolder()->getId())
                                   ->getAccessLevel();

        } catch (\Schoolbox\Mapper\Exception $e) {
            // no accesseses found...you get nothin'
            $data['access'] = UserAccess::ACCESS_DENY;
        }

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }
}
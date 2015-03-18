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
use Schoolbox\Collection;
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

    /** @var Config\Loader */
    protected $conf;

    /** @var TaskMapper */
    protected $taskMapper;


    /**
     * @param Config\Loader $conf
     * @return self
     */
    public function setConfig($conf)
    {
        $this->conf = $conf;
        return $this;
    }


    /**
     * @param Resource\FileMapper $fileMapper
     * @return self
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * @param Resource\Folder\Mapper $folderMapper
     * @return self
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }


    /**
     * @param RoleAccessMapper $roleAccessMapper
     * @return self
     */
    public function setRoleAccessMapper($roleAccessMapper)
    {
        $this->roleAccessMapper = $roleAccessMapper;
        return $this;
    }


    /**
     * @param TaskMapper $taskMapper
     * @return self
     */
    public function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
        return $this;
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return self
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * @param UserMapper $userMapper
     * @return self
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }




    /**
     * @param int $folderId
     * @return array
     */
    protected function getFolderData($folderId)
    {
        $sessionUser = $this->getSession()->getAuthUser();

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

        $folder->loadTeachers();

        $access = $this->getAccess($sessionUser, $folderId);

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
            'user'   => $sessionUser,
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
        $sessionUser = $this->getSession()->getAuthUser();

        // if a folder ID was supplied, stash that in session then redirect
        if ($folderId) {
            $this->getSession()->set('resourcesID', $folderId);

            $this->getResponse()->redirect->to(
                $this->getRouter()->generate('resourceBrowser.browse', array())
            );
        }

        $folderId = $this->getSession()->get('resourcesID', 0);

        $tree = new Resource\Folder\TreeBuilder(
            $this->folderMapper,
            $this->taskMapper,
            $this->userAccessMapper,
            $this->roleAccessMapper,
            $sessionUser
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
        $sessionUser = $this->getSession()->getAuthUser();

        /** @var Resource\File $file */
        $file = $this->fileMapper->findById($fileId);
        // urgh...
        $file->setOwner($this->userMapper->findById($file->getOwner()->getId()));

        $data = [
            'file'   => $file,
            'access' => $this->getAccess($sessionUser, $file->getFolder()->getId())
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/file/:fileIds/info
     * @param $fileIds
     */
    public function showMultiFileInfo($fileIds)
    {
        $fileIds   = json_decode("[$fileIds]", true);
        $files     = $this->fileMapper->findByColumn('id', $fileIds);
        $folders   = new Collection($files->getColumnValues('folder'));
        $folderIds = array_unique($folders->getColumnValues('id'));


        if ($this->getUser()->isSuperuser()) {
            $access = UserAccess::ACCESS_FULL;
        } else {
            $access = $this->userAccessMapper->userHasMinimumAccessToAll($this->getUser()->getId(), $folderIds, UserAccess::ACCESS_WRITE)
                ? UserAccess::ACCESS_WRITE
                : UserAccess::ACCESS_DENY;
        }

        $data = [
            'files'  => $fileIds,
            'user'   => $this->getUser(),
            'access' => $access
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showMultiFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @param User $user
     * @param int  $folderId
     * @return int
     */
    protected function getAccess($user, $folderId)
    {
        try {
            return $this->userAccessMapper
                ->getAccess($user, $folderId)
                ->getAccessLevel();

        } catch (\Schoolbox\Mapper\Exception $e) {
            try {
                return $this->roleAccessMapper
                    ->getAccess($user, $folderId)
                    ->getType();

            } catch (\Schoolbox\Mapper\Exception $e) {
                // no accesseses found...you get nothin'
                return null;
            }
        }
    }

}
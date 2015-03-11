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

use Schoolbox\Permission\Access\RoleAccessMapper;
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


    function __construct()
    {
        // TODO inject deps
        $this->folderMapper     = Resource\Folder\Mapper::getInstance();
        $this->fileMapper       = Resource\FileMapper::getInstance();
        $this->userAccessMapper = UserAccessMapper::getInstance();
        $this->roleAccessMapper = RoleAccessMapper::getInstance();
        $this->userMapper       = UserMapper::getInstance();
    }


    /**
     * @param int $folderId
     * @return array
     */
    protected function getFolderData($folderId)
    {
        $folder = $this->folderMapper->findById($folderId);
        $admins = $this->userAccessMapper->findAdminsInCategory($folder);
        $roles  = $this->roleAccessMapper->findAllForFolder($folder);

        if ($admins->count()) {
            $admin = $this->userMapper->findById($admins->first()->getId());
        } else {
            $admin = new User();
            $admin->setFirstname('Nobody');
        }

        return [
            'folder' => $folder,
            'admin'  => $admin,
            'roles'  => $roles
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
        $data = $this->getFolderData($folderId);

        // add files to that
        $data['files'] = $this->fileMapper->findByFolderId($folderId)->getAll();

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
        $data = [ 'file' => $this->fileMapper->findById($fileId) ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }
} 
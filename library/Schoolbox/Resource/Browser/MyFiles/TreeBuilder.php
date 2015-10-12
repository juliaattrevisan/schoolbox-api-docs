<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\MyFiles;


use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\User;

class TreeBuilder extends AbstractTreeBuilder
{

    /** @var User */
    protected $user;


    /**
     * @param Folder\ILocator $locator
     * @param User            $user
     */
    public function __construct(Folder\ILocator $locator, User $user)
    {
        parent::__construct($locator, 'myfile');
        $this->user = $user;
    }


    /**
     * Return the given folder's href
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderHref(IFolder $folder)
    {
        return '/cms/portfolio/' . $folder->getId();
    }


    /**
     * Return the given file's href
     *
     * @param Resource\AbstractFile $file
     * @return string
     */
    protected function getFileHref(Resource\AbstractFile $file)
    {
        return '/cms/sendMyFile.php?id=' . $file->getId();
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected function getNodeFolders($nodeId)
    {
        /** @var Mapper $locator */
        $locator = $this->folderLocator;

        // TODO cache this
        return $locator->findByColumnList([
            'owner'  => $this->user->getId(),
            'parent' => $nodeId,
        ]);
    }


    /**
     * @param int $nodeId
     * @return Collection|Resource\AbstractFile[]
     */
    protected function getNodeFiles($nodeId)
    {
        /** @var Mapper $locator */
        $locator = $this->fileLocator;

        return $locator->findByColumnList([
            'owner' => $this->user->getId(),
            'parent' => $nodeId
        ]);
    }


    /**
     * @param int $nodeId
     * @return string
     */
    protected function getFolderPermission($nodeId)
    {
        return $this->getPermissionString(UserAccess::ACCESS_FULL);
    }


    /**
     * @param int $nodeId
     * @return string
     */
    protected function getFilePermission($nodeId)
    {
        return $this->getPermissionString(UserAccess::ACCESS_FULL);
    }


}
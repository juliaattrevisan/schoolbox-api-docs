<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Resources;

use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\User;


class TreeBuilder extends AbstractTreeBuilder
{

    /** @var TaskMapper */
    protected $taskMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var RoleAccessMapper */
    protected $roleAccessMapper;

    /** @var User */
    protected $user;


    /**
     * @param Folder\ILocator  $locator
     * @param TaskMapper       $taskMapper
     * @param UserAccessMapper $userAccessMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param User             $user
     */
    public function __construct(Folder\ILocator $locator, TaskMapper $taskMapper, $userAccessMapper, $roleAccessMapper, $user)
    {
        parent::__construct($locator);

        $this->rootNodeLabel    = 'Resources';
        $this->taskMapper       = $taskMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->user             = $user;
    }


    /**
     * @param IFolder|Folder\Model $folder
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFolderNode(IFolder $folder)
    {
        // Get the permission this user has on this folder.
        // No permission: don't show it at all!
        $permission = $this->getFolderPermission($folder->getId());
        if (empty($permission)) {
            throw new Resource\Exception("No permission for this node");
        }

        // Type: homepage or folder
        // Could be a course as well, if any child has tasks.
        $childIds = $this->folderLocator
                         ->findByParentId($folder->getId())
                         ->getColumnValues('id');

        if ($this->taskMapper->countByColumn('category_id', $childIds)) {
            $type = 'course';
        } else {
            $type = $folder->hasHomepage() ? 'homepage' : 'folder';
        }

        // Build the name of the category
        $name       = $folder->getName();
        $codeString = $folder->getCodeString();
        if ($codeString != '' && $codeString != $name) {
            $name .= ' (' . $codeString . ')';
        }

        // Output something.
        return $this->generateFolderArray($folder, $type, $name,
            [
                'data-permission' => $permission,
                'data-type'       => $type
            ],
            [ 'href' => '/homepage/' . $folder->getId() ]
        );
    }


    /**
     * @param Resource\File\Model $file
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFileNode(File\IFile $file)
    {
        $permission = $this->getFilePermission($file->getId());
        if (empty($permission)) {
            throw new Resource\Exception("No permission for this node");
        }

        return $this->generateFileArray($file, $file->getTitle(),
            [
                'data-permission' => $permission,
                'data-type'       => 'file'
            ],
            [
                'href' => '/send.php?id=' . $file->getId(),
            ]
        );
    }


    /**
     * @param Resource\File\Model $file
     * @param string              $name
     * @param array               $liAttributes
     * @param array               $aAttributes
     * @return array
     */
    protected function generateFileArray(Resource\File\IFile $file, $name = null, array $liAttributes = null, array $aAttributes = null)
    {
        if (empty($liAttributes['data-id'])) {
            $liAttributes['data-id'] = $file->getId();
        }

        return $this->generateNodeArray("sbfsfile-{$file->getId()}", $file->getFolder()->getId(), 'file', $name ?: $file->getTitle(), $liAttributes, $aAttributes);
    }


    /**
     * @param int $folderId
     * @return string
     */
    protected function getFolderPermission($folderId)
    {
        // check user access first, then role access if that fails
        try {
            $access = $this->userAccessMapper
                ->getAccess($this->user, $folderId)
                ->getAccessLevel();

        } catch (Mapper\Exception $e) {
            try {
                $access = $this->roleAccessMapper
                    ->getAccess($this->user, $folderId)
                    ->getType();

            } catch (Mapper\Exception $e) {
                $access = 0;
            }
        }

        return $this->getPermissionString($access);
    }


    /**
     * @param int $nodeId
     * @return string
     */
    protected function getFilePermission($nodeId)
    {
        if (!$this->fileLocator) {
            return $this->getPermissionString(UserAccess::ACCESS_DENY);
        }

        /** @var File\Model $file */
        $file = $this->fileLocator->findById($nodeId);

        // retrieve the folder permission
        $folderPerm = $this->getFolderPermission($file->getFolder()->getId());

        // if read and hidden, cast to none; otherwise leave as-is
        if ($folderPerm == 'read' && $file->isHidden()) {
            $folderPerm = '';
        }

        return $folderPerm;
    }


    /**
     * Gets a permission string out of a permission code returned from
     * $this->handler->getPermission().
     *
     * @param  $code
     *
     * @return string
     */
    protected function getPermissionString($code)
    {
        switch ($code) {
            case UserAccess::ACCESS_FULL:
                return 'full';
                break;
            case UserAccess::ACCESS_WRITE:
                return 'write';
                break;
            case UserAccess::ACCESS_READ:
                return 'read';
                break;
            default:
                break;
        }
        return '';
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected function getNodeFolders($nodeId)
    {
        // TODO cache this
        return $this->folderLocator->findByParentId($nodeId);
    }


    /**
     * @param int $nodeId
     * @return Collection|File\IFile[]
     */
    protected function getNodeFiles($nodeId)
    {
        // TODO cache this
        if ($this->fileLocator) {
            return $this->fileLocator->findByFolderId($nodeId);
        }
        return new Collection();
    }

}

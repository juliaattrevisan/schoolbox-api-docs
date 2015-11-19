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
     * @param string           $rootLabel
     */
    public function __construct(Folder\ILocator $locator, TaskMapper $taskMapper, $userAccessMapper, $roleAccessMapper, $user, $rootLabel = 'Resources')
    {
        parent::__construct($locator, 'sbfsfile', $rootLabel);

        $this->taskMapper       = $taskMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->user             = $user;
    }


    /**
     * Return the folder type for the given node. By default is simply 'folder'.
     *
     * @param IFolder|Folder\Model $folder
     * @return string
     */
    protected function getFolderType(IFolder $folder)
    {
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

        return  $type;
    }


    /**
     * Return the given folder's name. By default is just its name...
     *
     * @param IFolder|Folder\Model $folder
     * @return string
     */
    protected function getFolderName(IFolder $folder)
    {
        // Build the name of the category
        $name       = $folder->getName();
        $codeString = $folder->getCodeString();
        if ($codeString != '' && $codeString != $name) {
            $name .= " ($codeString)";
        }

        return $name;
    }


    /**
     * Return the given folder's href
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderHref(IFolder $folder)
    {
        return '/homepage/' . $folder->getId();
    }


    /**
     * Return the given file's href
     *
     * @param Resource\AbstractFile $file
     * @return string
     */
    protected function getFileHref(Resource\AbstractFile $file)
    {
        return '/send.php?id=' . $file->getId();
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
                $access = UserAccess::ACCESS_DENY;
            }
        }

        return $this->getPermissionString($access);
    }


    /**
     * @param  Resource\AbstractFile $file
     * @return string
     */
    protected function getFilePermission($file)
    {
        if (!$this->fileLocator) {
            return $this->getPermissionString(UserAccess::ACCESS_DENY);
        }

        // retrieve the folder permission
        $folderPerm = $this->getFolderPermission($file->getFolder()->getId());

        // if read and hidden, cast to none; otherwise leave as-is
        if ($folderPerm == $this->getPermissionString(UserAccess::ACCESS_READ)
            && $file->isHidden()
        ) {
            $folderPerm = $this->getPermissionString(UserAccess::ACCESS_DENY);
        }

        return $folderPerm;
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
     * @return Collection|Resource\AbstractFile[]
     */
    protected function getNodeFiles($nodeId)
    {
        // TODO cache this
        if ($this->fileLocator) {
            return $this->fileLocator->findByFolderId($nodeId);
        }
        return new Collection();
    }


    /**
     * @param Resource\AbstractFile $file
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFileNode($file)
    {
        $permission = $this->getFilePermission($file);
        if (empty($permission)) {
            throw new Resource\Exception("No permission for this node");
        }
        if ($file instanceof Resource\AbstractFile) {
            return $this->generateFileArray($file, $file->getTitle(),
                [
                    'data-permission' => $permission,
                    'data-type'       => 'file'
                ],
                [
                    'href' => $this->getFileHref($file),
                ]
            );
        } elseif ($file instanceof Resource\Link) {
            return $this->generateNodeArray(
                $file->getId(),
                $file->getFolder()->getId(),
                'link',
                $file->getDescription(),
                [
                    'data-id'         => $file->getId(),
                    'data-permission' => $permission,
                    'data-type'       => 'link'
                ],
                [
                    'href' => $file->getHref(),
                ]
            );
        } else {
            return [];
        }
    }

}

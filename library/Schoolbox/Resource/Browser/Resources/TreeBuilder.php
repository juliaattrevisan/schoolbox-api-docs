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
    protected function buildNode(IFolder $folder)
    {
        // Get the permission this user has on this folder.
        // No permission: don't show it at all!
        $permission = $this->getNodePermission($folder->getId());
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
        return $this->generateNodeArray($folder, $type, $name,
            [ 'data-permission' => $permission ],
            [ 'href' => '/homepage/' . $folder->getId() ]
        );
    }


    /**
     * @param int $folderId
     * @return string
     */
    protected function getNodePermission($folderId)
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
    protected function getNodeChildren($nodeId)
    {
        // TODO cache this
        return $this->folderLocator->findByParentId($nodeId);
    }

}

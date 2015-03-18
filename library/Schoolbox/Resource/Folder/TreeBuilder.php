<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;

use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;


class TreeBuilder 
{

    /** @var Mapper */
    protected $mapper;

    /** @var TaskMapper */
    protected $taskMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var RoleAccessMapper */
    protected $roleAccessMapper;

    /** @var User */
    protected $user;

    /**
     * @param Mapper           $mapper
     * @param TaskMapper       $taskMapper
     * @param UserAccessMapper $userAccessMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param User             $user
     */
    public function __construct(Mapper $mapper, TaskMapper $taskMapper, $userAccessMapper, $roleAccessMapper, $user)
    {
        $this->mapper           = $mapper;
        $this->taskMapper       = $taskMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->roleAccessMapper = $roleAccessMapper;
        $this->user             = $user;
    }

    /**
     * Builds a list of child nodes for $categoryId
     *
     * @param  mixed $categoryId
     *
     * @return array
     */
    public function buildChildNodes($categoryId)
    {

        $output = array();

        // Did I ask for the root node? get it
        if ($categoryId === '#') {
            $output[] = array(
                'id'       => '0',
                'parent'   => '#',
                'text'     => 'Resources',
                'children' => count($this->mapper->findByParent(0)) > 0,
                'type'     => 'root',
                'li_attr'  => array(
                    'data-permission' => $this->getFolderPermission($categoryId),
                ),
            );
        } else {
            // Child nodes
            foreach ($this->mapper->findByParent($categoryId) as $category) {
                // Get the permission this user has on this folder.
                // No permission: don't show it at all!
                $permission = $this->getFolderPermission($category->getId());
                if (empty($permission)) {
                    continue;
                }

                // Type: homepage or folder
                // Could be a course as well, if any child has tasks.
                $type     = $category->hasHomepage() ? 'homepage' : 'folder';
                $children = $this->mapper->findByParent($category->getId());
                $childIds = $children->getColumnValues('id');
                if ($this->taskMapper->countByColumn('category_id', $childIds)) {
                    $type = 'course';
                }

                // Build the name of the category
                $name       = $category->getName();
                $codeString = $category->getCodeString();
                if ($codeString != '' && $codeString != $name) {
                    $name .= ' (' . $codeString . ')';
                }

                // Output something.
                $output[] = array(
                    'id'       => $category->getId(),
                    'parent'   => $category->getParent()->getId() ?: '0',
                    'text'     => $name,
                    'children' => count($children) > 0,
                    'type'     => $type,
                    'a_attr'   => array(
                        'href' => '/homepage/' . $category->getId(),
                    ),
                    'li_attr'  => array(
                        'data-permission' => $permission,
                    ),
                );
            }
        }

        return $output;
    }

    /**
     * Builds a partial tree.
     *
     * This method loads $categoryId's children, then its parent's direct
     * children, and repeats all the way up to the parent node.
     *
     * @param  mixed $categoryId
     *
     * @return array
     */
    public function buildPartialTree($categoryId)
    {

        // build a list of parentage
        $parentList[] = $parentId = $categoryId;
        $i            = 0;
        while ($parentId > 0) {
            if ($i++ > 100) {
                break;
            }
            try {
                $parentList[] = $parentId = $this->mapper->findById($parentId)->getParent()->getId();
            } catch (\Schoolbox\Mapper\Exception $e) {
                break;
            }
        }

        // Build the root node
        $partialTree = $this->buildChildNodes('#');

        // Build the parent nodes
        foreach ($parentList as $parentCategoryId) {
            $partialTree = array_merge(
                $partialTree,
                $this->buildChildNodes($parentCategoryId)
            );
        }

        // Open $categoryId's node and its parents
        foreach ($partialTree as &$leaf) {
            $leaf['state']['opened']   = in_array($leaf['id'], $parentList) || $categoryId == 0;
            $leaf['state']['selected'] = ($leaf['id'] == $categoryId);
        }
        unset($leaf);

        return $partialTree;
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

        } catch (\Schoolbox\Mapper\Exception $e) {
            try {
                $access = $this->roleAccessMapper
                    ->getAccess($this->user, $folderId)
                    ->getType();

            } catch (\Schoolbox\Mapper\Exception $e) {
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
} 
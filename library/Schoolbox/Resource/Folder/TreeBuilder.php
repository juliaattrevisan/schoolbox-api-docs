<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;

use Group as CategoryHandler;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\UserAccess;


class TreeBuilder 
{

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var CategoryHandler
     */
    protected $handler;

    /**
     * @var TaskMapper
     */
    protected $taskMapper;

    /**
     * @param Mapper          $mapper
     * @param TaskMapper      $taskMapper
     * @param CategoryHandler $handler
     */
    public function __construct(Mapper $mapper, TaskMapper $taskMapper, CategoryHandler $handler)
    {
        $this->mapper     = $mapper;
        $this->handler    = $handler;
        $this->taskMapper = $taskMapper;
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
                    'data-permission' => $this->getPermissionString($this->handler->getPermission(0)),
                ),
            );
        } else {
            // Child nodes
            foreach ($this->mapper->findByParent($categoryId) as $category) {
                // Get the permission this user has on this folder.
                // No permission: don't show it at all!
                $permission = $this->getPermissionString($this->handler->getPermission($category->getId()));
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
            $leaf['state']['opened']   = in_array($leaf['id'], $parentList);
            $leaf['state']['selected'] = ($leaf['id'] == $categoryId);
        }
        unset($leaf);

        return $partialTree;
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
<?php
/**
 * Builder for a category tree.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Assessment\Course\TaskMapper;
use Group as CategoryHandler;

class CategoryTree {

    /**
     * @var CategoryMapper
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
     * @param CategoryMapper  $mapper
     * @param TaskMapper      $taskMapper
     * @param CategoryHandler $handler
     */
    public function __construct(CategoryMapper $mapper, TaskMapper $taskMapper, CategoryHandler $handler) {
        $this->mapper     = $mapper;
        $this->handler    = $handler;
        $this->taskMapper = $taskMapper;
    }

    /**
     * Builds a node out of this category?
     *
     * @param  mixed $categoryId
     * @return array
     */
    public function createTree($categoryId) {

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
        }
        else {

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

                // Output something.
                $output[] = array(
                    'id'       => $category->getId(),
                    'parent'   => $category->getParent()->getId() ?: '0',
                    'text'     => $category->getName(),
                    'children' => count($children) > 0,
                    'type'     => $type,
                    'a_attr'   => array(
                        'href' => '/homepage/'.$category->getId(),
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
     * gets a permission string out of something
     *
     * @param  $code
     * @return string
     */
    protected function getPermissionString($code) {
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
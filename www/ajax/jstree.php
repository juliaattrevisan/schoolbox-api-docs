<?php

session_start();
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\TaskMapper;

// #: get base items
$DEPRECATEDHANDLER = new Group();
$categoryMapper = CategoryMapper::getInstance();

$output = array();
$parentId = $_GET['parent'];
if ($parentId === '#') {
    // Root node
    // Can this user read or write to the root node?
    $output[] = array(
        'id'       => '0',
        'text'     => 'Resources',
        'children' => count($categoryMapper->findByParent(0)) > 0,
        'type'     => 'root',
        'li_attr'  => array(
            'data-permission' => 'read',
        ),
    );
}
else {
    // Children of root node
    $list = $categoryMapper->findByParent($parentId);
    foreach ($list as $category) {

        // Get the permission this user has on this folder.
        $permission = '';
        switch ($DEPRECATEDHANDLER->getPermission($category->getId())) {
            case FULL_ACCESS:
                $permission = 'full';
                break;
            case WRITE_ACCESS:
                $permission = 'write';
                break;
            case READ_ACCESS:
                $permission = 'read';
                break;
            default:
                break;
        }

        // No permission: don't show it at all!
        if (empty($permission)) {
            continue;
        }

        // Type: homepage or folder
        // Could be a course as well, if any child has tasks.
        $type     = $category->hasHomepage() ? 'homepage' : 'folder';
        $children = $categoryMapper->findByParent($category->getId());
        $childIds = $children->getColumnValues('id');
        if (TaskMapper::getInstance()->countByColumn('category_id', $childIds)) {
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

header('Content-Type: application/json');
echo json_encode($output);

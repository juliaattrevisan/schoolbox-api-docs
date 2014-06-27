<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\TaskMapper;

// #: get base items
$categoryMapper = CategoryMapper::getInstance();

$output = array();
$parentId = $_GET['parent'];
if ($parentId === '#') {
    // Root node
    $output[] = array(
        'id'       => '0',
        'text'     => 'Resources',
        'children' => count($categoryMapper->findByParent(0)) > 0,
        'type'     => 'root',
    );
}
else {
    // Children of root node
    $list = $categoryMapper->findByParent($parentId);
    foreach ($list as $category) {

        // Type: homepage or folder
        // Could be a course as well, if any child has tasks.
        $type     = $category->hasHomepage() ? 'homepage' : 'folder';
        $children = $categoryMapper->findByParent($category->getId());
        $childIds = $children->getColumnValues('id');
        if (TaskMapper::getInstance()->countByColumn('category_id', $childIds)) {
            $type = 'course';
        }
        $output[] = array(
            'id'       => $category->getId(),
            'parent'   => $category->getParent()->getId() ?: '0',
            'text'     => $category->getName(),
            'children' => count($children) > 0,
            'type'     => $type,
        );
    }
}

header('Content-Type: application/json');
echo json_encode($output);

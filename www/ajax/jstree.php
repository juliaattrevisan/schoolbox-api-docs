<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\Resource\CategoryMapper;

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
    );
}
else {
    // Children of root node
    $list = $categoryMapper->findByParent($parentId);
    foreach ($list as $category) {
        // Does this item have categories?
        $output[] = array(
            'id'     => $category->getId(),
            'parent' => $category->getParent()->getId() ?: '0',
            'text'   => $category->getName(),
            // I just need to know if there are children for this node.
            'children' => count($categoryMapper->findByParent($category->getId())) > 0,
        );
    }
}

header('Content-Type: application/json');
echo json_encode($output);

<?php

session_start();
require_once __DIR__ . '/../../include/Bootstrap.php';

$folderHandler = new MyFolder();

// Root node
$folderId = $_GET['relationID'];

$output = array();
if ($folderId === '#') {
    $output[] = array(
        'id'       => '0',
        'parent'   => '#',
        'text'     => 'Resources',
        'children' => (bool)$folderHandler->hasChildren(0),
        'type'     => 'root',
        'li_attr'  => array(
            'data-permission' => 'full',
        ),
    );
}
else {
    $folderList = $folderHandler->getFolderList($folderId);
    if (is_array($folderList)) {
        foreach ($folderList as $folder) {
            $output[] = array(
                'id'       => $folder['id'],
                'parent'   => $folder['parent'],
                'text'     => $folder['name'],
                'children' => (bool)$folderHandler->hasChildren($folder['id']),
                'type'     => 'folder',
                'li_attr'  => array(
                    'data-permission' => 'full',
                ),
            );
        }
    }
}

header("Content-type: application/json");
print json_encode($output);
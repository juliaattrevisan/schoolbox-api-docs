<?php

require_once __DIR__ . '/../../include/Bootstrap.php';
// Open session after bootstrap, otherwise $_SESSION[user] won't be a user.
session_start();

$folderHandler = PortfolioWebDAV::factory();
$folderId      = $_GET['relationID'];
$output        = array();
if ($folderId === '#') {
    $output[] = array(
        'id'       => '0',
        'parent'   => '#',
        'text'     => 'Top',
        'children' => $folderHandler->hasChildren($folder['id']),
        'type'     => 'root',
        'li_attr'  => array(
            'data-permission' => 'full',
        ),
    );
}
else {
    foreach ($folderHandler->getFolderList($folderId) as $folder) {
        $children = (int)$folderHandler->hasChildren($folder['id']);
        $output[] = array(
            'id'       => $folder['id'],
            'text'     => $folder['description'],
            'parent'   => $folder['parent'],
            'children' => $folderHandler->hasChildren($folder['id']),
            'type'     => 'folder',
            'li_attr'  => array(
                'data-permission' => 'full',
            ),
        );
    }
}
header("Content-type: application/json");
echo json_encode($output);

<?php


$this->setFile(TEMPLATE_content, 'group/bulk.html');

$this->setTitle('Add Bulk Members');
$this->setTab('resources');

$folderMapper = new \Schoolbox\Resource\CategoryMapper();

if (is_numeric($this->arg[0])) {
    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        error(19);
    }

    $userAccessMapper = new \Schoolbox\Permission\Access\UserAccessMapper();

    $isFolderOwner = $userAccessMapper->isFolderOwner($_SESSION['user'], $folder);

    // check access
    if (!$isFolderOwner && !($_SESSION['user']->isSuperuser())) {
        error(19);
    }

    $this->setVar(array(
        'CATEGORY_NAME' => Scrub::text($folder->getName()),
        'CATEGORY_ID'   => $folder->getId(),
    ));

    $trail = new \Schoolbox\Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Manage Group',
            'href' => "/group/modify/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Add Bulk Members',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    error(2);
}

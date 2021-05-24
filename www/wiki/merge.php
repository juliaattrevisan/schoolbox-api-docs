<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$wikiHandler = new Wiki();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

$this->setTitle('Modify Wiki');
$this->setTab('resources');
$this->setFile(TEMPLATE_content, 'wiki/merge.html');

if (is_numeric($this->arg[0])) {
    $wiki = $wikiHandler->getByID($this->arg[0]);
    if (!isset($wiki['wikiID'])) {
        error(101);
    }

    try {
        $folder = FolderMapper::getInstance()->findById($wiki['categoryID']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    // check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < UserAccess::ACCESS_READ) {
        error(19);
    }

    // check locked
    if ($wiki['locked'] == 'true' && $permission < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    $previousRevision = $wikiHandler->getLatestRevision($wiki['wikiID']);

    $output = array('ACTION'      => 'Modify',
                    'WIKI_ID'     => $previousRevision['wikiID'],
                    'SLUG'        => $previousRevision['slug'],
                    'CATEGORY_ID' => $previousRevision['categoryID'],
                    'FOLDER_NAME' => Scrub::text($folder->getName()),
                    'HEADING'     => Scrub::text($previousRevision['heading']),
                    'BODY'        => $previousRevision['body'],
                    'REVISION'    => $wiki['latestRevision'],
                    );
    $this->setVar($output);

    $this->setVar(array(
        'YOURS_HEADING' => $wiki['heading'],
        'YOURS_BODY' => $wiki['body'],
    ));

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Merge Wiki',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
    // check permission
    if ($groupHandler->getPermission($this->arg[1]) < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    try {
        $folder = FolderMapper::getInstance()->findById($this->arg[1]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    $output = array(
        'ACTION'        => 'Add',
        'WIKI_ID'       => '',
        'CATEGORY_ID'   => $this->arg[1],
        'HEADING'       => Scrub::text($wiki['heading']),
        'BODY'          => $wiki['body'],
        'YOURS_HEADING' => '',
        'YOURS_BODY'    => '',
    );

    $this->setVar($output);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Add Wiki',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    error(2);
}

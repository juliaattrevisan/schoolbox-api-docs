<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$wikiHandler  = new Wiki();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

$this->setTitle('Modify Wiki');
$this->setTab('resources');
$this->setFile(TEMPLATE_content, 'wiki/modify.html');

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

    //Collate the slugs of wikis on this page - used for validation
    $slugs = gimmeDaWikiSlugs($wikiHandler, $folder);

    //Unset current wiki in validation list
    unset($slugs[$wiki['wikiID']]);

    $output = array(
        'ACTION'      => 'Modify',
        'WIKI_ID'     => $wiki['wikiID'],
        'SLUG'        => $wiki['slug'],
        'CATEGORY_ID' => $wiki['categoryID'],
        'FOLDER_NAME' => Scrub::text($folder->getName()),
        'HEADING'     => Scrub::text($wiki['heading']),
        'BODY'        => Scrub::text($wiki['body']),
        'REVISION'    => $wiki['latestRevision'],
        'SLUG_LIST'   => json_encode(array_values($slugs))
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
            'text' => 'Wiki',
            'href' => "/wiki/{$folder->getId()}/{$wiki['slug']}",
            'class' => '',
        ],
        [
            'text' => 'Modify',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    /* Check permission
     *
     * First check that Allow Create is active. If it is, continue;
     * otherwise proceed to the usual getPermission()
     */
    $value = \Component_ConfigMapper::getInstance()->findValue('allowCreate', $this->arg[2]);

    try {
        $folder = FolderMapper::getInstance()->findById($this->arg[1]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    if (((int)$value === 0) && ($groupHandler->getPermission($this->arg[1]) < UserAccess::ACCESS_WRITE)) {
        error(19);
    }

    // slug to title: let's just say all - convert to spaces, and capitalize all words
    $slug  = strlen($this->arg[3]) ? $this->arg[3] : '';
    $title = ucwords(str_replace('-', ' ', $slug));

    //Collate the slugs of wikis on this page - used for validation
    $slugs = gimmeDaWikiSlugs($wikiHandler, $folder);

    $this->setVar(
        array(
            'ACTION'      => 'Add',
            'WIKI_ID'     => '',
            'CATEGORY_ID' => $this->arg[1],
            'FOLDER_NAME' => Scrub::text($folder->getName()),
            'HEADING'     => $title,
            'BODY'        => '',
            'REVISION'    => '',
            'INSTANCE_ID' => $this->arg[2],
            'SLUG'        => '',
            'SLUG_LIST'   => json_encode(array_values($slugs))
        )
    );

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
            'text' => 'New Wiki',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    error(2);
}

/**
 * @param Wiki $wikiHandler
 * @param Schoolbox\Resource\Folder\Model $folder
 */
function gimmeDaWikiSlugs($wikiHandler, $folder)
{
    //Collate the slugs of wikis on this page - used for validation
    $wikis = $wikiHandler->getByCategory($folder->getId());

    $slugs = [];

    if (!empty($wikis)) {
        foreach ($wikis as $wiki) {
            $slugs[$wiki['wikiID']] = $wiki['slug'];
        }
    }

    return $slugs;
}

<?php

use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

$wikiHandler  = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

$this->setTitle('Modify Wiki');
$this->setTab('resources');
$this->setFile(TEMPLATE_content, 'wiki/modify.html');

if (is_numeric($this->arg[0])) {
    $wiki = $wikiHandler->getByID($this->arg[0]);

    if (!isset($wiki['wikiID'])) {
        error(101);
    }

    try{
        $folder = FolderMapper::getInstance()->findById($wiki['categoryID']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    // check permission
    if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS) {
        error(19);
    }

    // check locked
    if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS) {
        error(19);
    }

    $output = array(
        'ACTION'      => 'Modify',
        'WIKI_ID'     => $wiki['wikiID'],
        'SLUG'        => $wiki['slug'],
        'CATEGORY_ID' => $wiki['categoryID'],
        'FOLDER_NAME' => Scrub::text($folder->getName()),
        'HEADING'     => Scrub::text($wiki['heading']),
        'BODY'        => Scrub::text($wiki['body']),
        'REVISION'    => $wiki['latestRevision'],
    );

    $this->setVar($output);
} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    /* Check permission
     *
     * First check that Allow Create is active. If it is, continue;
     * otherwise proceed to the usual getPermission()
     */
    $value = \Component_ConfigMapper::getInstance()->findValue('allowCreate', $this->arg[2]);

    try{
        $folder = FolderMapper::getInstance()->findById($this->arg[1]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    if (((int)$value === 0) && ($groupHandler->getPermission($this->arg[1]) < WRITE_ACCESS)) {
        error(19);
    }

    // slug to title: let's just say all - convert to spaces, and capitalize all words
    $slug  = strlen($this->arg[3]) ? $this->arg[3] : '';
    $title = ucwords(str_replace('-', ' ', $slug));

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
        )
    );
} else {
    error(2);
}

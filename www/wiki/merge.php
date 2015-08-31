<?php

use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Breadcrumbs;

$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

$this->setTitle('Modify Wiki');
$this->setTab('resources');
$this->setFile(TEMPLATE_content, 'wiki/merge.html');

if (is_numeric($this->arg[0])) {

	$wiki = $wikiHandler->getByID($this->arg[0]);
	if (!isset($wiki['wikiID']))
		error(101);

	try{
		$folder = FolderMapper::getInstance()->findById($wiki['categoryID']);
	} catch (\Schoolbox\Mapper\Exception $e) {
		error(2);
	}

	// check permission
	if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS)
		error(19);

	// check locked
	if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS)
		error(19);

    $previousRevision = $wikiHandler->getPreviousRevision($wiki['wikiID'], $wiki['latestRevision']);

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
	if ($groupHandler->getPermission($this->arg[1]) < WRITE_ACCESS)
		error(19);

	$output = array('ACTION'		=> 'Add',
					'WIKI_ID'		=> '',
					'CATEGORY_ID'	=> $this->arg[1],
					'HEADING'		=> Scrub::text($wiki['heading']),
					'BODY'			=> $wiki['body']
					);
	$this->setVar($output);


} else
	error(2);

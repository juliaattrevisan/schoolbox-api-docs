<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);

$this->setTitle('Modify Wiki');
$this->setTab('resources');
$this->setFile(TEMPLATE_content, 'wiki/modify.html');

if (is_numeric($this->arg[0])) {

	$wiki = $wikiHandler->getByID($this->arg[0]);
	if (!isset($wiki['wikiID']))
		error(101);

	// check permission
	if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS)
		error(19);

	// check locked
	if ($wiki['locked'] == 'true' && $permission < WRITE_ACCESS)
		error(19);

	$output = array('ACTION'		=> 'Modify',
					'WIKI_ID'		=> $wiki['wikiID'],
					'CATEGORY_ID'	=> $wiki['categoryID'],
					'HEADING'		=> Scrub::text($wiki['heading']),
					'BODY'			=> $wiki['body'],
                    'REVISION'      => $wiki['latestRevision'],
					);
	$this->setVar($output);


} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

	// check permission
	if ($groupHandler->getPermission($this->arg[1]) < WRITE_ACCESS)
		error(19);

	$output = array('ACTION'                        => 'Add',
                        'WIKI_ID'	=> '',
                        'CATEGORY_ID'	=> $this->arg[1],
                        'HEADING'	=> Scrub::text($wiki['heading']),
                        'BODY'		=> $wiki['body'],
                        'REVISION'      => '',
                        );
	$this->setVar($output);


} else
	error(2);

?>
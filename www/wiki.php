<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);


if (is_numeric($this->arg[0])) {

	$this->setFile(TEMPLATE_content, 'wiki.html');
	$this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');
	$this->setBlock(TEMPLATE_content, 'delete_link', 'delete_link_block');
	$this->setBlock(TEMPLATE_content, 'revert_link', 'revert_link_block');
	$this->setBlock(TEMPLATE_content, 'lock_link', 'lock_link_block');
	$this->setBlock(TEMPLATE_content, 'export_link', 'export_link_block');
	$this->setBlock(TEMPLATE_content, 'revision_list', 'revision_list_block');
	$this->setBlock('revision_list', 'revision_comparison', 'revision_comparison_block');

	$wiki = $wikiHandler->getByID($this->arg[0], $this->arg[1]);

	if (!isset($wiki['wikiID']))
		error(101);

	// check permission
	if (($permission = $groupHandler->getPermission($wiki['categoryID'])) < READ_ACCESS)
		error(19);

	$this->setTitle('Wiki');
	$this->setTab('resources');

	$userHandler = new User();

	// output wiki
	$output = array(
		'WIKI_ID' => $wiki['wikiID'],
		'CATEGORY_ID' => $wiki['categoryID'],
		'HEADING' => scrubText($wiki['heading']),
		'BODY' => scrubHTML($wiki['body']),
		'MODIFIED' => outputDate(Cfg::get('date_long'), $wiki['modified']),
		'AUTHOR' => $userHandler->formatName($wiki),
		'AUTHOR_ID' => $wiki['authorID'],
		'CURRENT_REVISION' => $wiki['revisionID'],
		'LOCKED' => $wiki['locked'] == 'true' ? '(Locked)' : '',
		'LOCK_UNLOCK' => $wiki['locked'] == 'true' ? 'Unlock' : 'Lock',
		'EXPORT' => 'Export',
	);

	$this->setVar($output);

	// output revisions
	$revisionList = $wikiHandler->getRevisionList($wiki['wikiID']);

	if (is_array($revisionList)) {
		$userHandler = new User();
		$i = 0;

		foreach ($revisionList as $revision) {
			$i++;

			$this->setVar(array(
					'WIKI_ID'           => $wiki['wikiID'],
					'REVISION_ID'       => $wiki['revisionID'],
					'REVISION_COMPARE'  => $revision['revisionID'],
					'REVISION_DATE'     => outputDate(Cfg::get('date_notime'), $revision['created']),
					'REVISION_HEADING'	=> scrubText($revision['heading']),
					'REVISION_AUTHOR'	=> $userHandler->formatName($revision),
					'REVISION_CURRENT'	=> $revision['revisionID']==$wiki['revisionID']?'revisionCurrent':'',
			));

			if ($wiki['revisionID'] == $revision['revisionID']) {
				$this->setVar('revision_comparison_block');
			}
			else {
				$this->parse('revision_comparison_block', 'revision_comparison');
			}

			$this->parse('revision_list_block', 'revision_list', true);

			if ($i == 1) {
				$finalRevision = $revision['revisionID'];
			}
		}
	}
	else {
		$this->setVar('revision_list_block', '');
	}

	// output links
	$this->setVar('revert_link_block', '');
	if ($wiki['revisionID'] != $finalRevision && $permission >= WRITE_ACCESS)
		$this->parse('revert_link_block', 'revert_link');

	$this->setVar('delete_link_block', '');
	if ($permission >= WRITE_ACCESS)
		$this->parse('delete_link_block', 'delete_link');

	$this->setVar('lock_link_block', '');
	if ($permission >= WRITE_ACCESS)
		$this->parse('lock_link_block', 'lock_link');

	$this->setVar('modify_link_block', '');
	if (($permission >= READ_ACCESS && $wiki['locked'] == 'false') || $permission >= WRITE_ACCESS)
		$this->parse('modify_link_block', 'modify_link');

	$this->setVar('export_link_block', '');
	if ($permission >= READ_ACCESS)
		$this->parse('export_link_block', 'export_link');

} else
	error(2);
?>
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

// pear libs
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/PEAR/Text/Diff.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/PEAR/Text/Diff/Renderer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/PEAR/Text/Diff/Renderer/inline.php';

$wiki  = new Wiki;
$group = new Group(HOMEPAGE_SERVICE);

if (is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'wiki/diff.html');
	$this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');
	$this->setBlock(TEMPLATE_content, 'delete_link', 'delete_link_block');
	$this->setBlock(TEMPLATE_content, 'revert_link', 'revert_link_block');
	$this->setBlock(TEMPLATE_content, 'lock_link', 'lock_link_block');
	$this->setBlock(TEMPLATE_content, 'revision_list', 'revision_list_block');
	$this->setBlock('revision_list', 'revision_comparison', 'revision_comparison_block');

	$from = $wiki->getByID($this->arg[0], $this->arg[1]);
	$to   = $wiki->getByID($this->arg[0], $this->arg[2]);

	if (!isset($from['wikiID']) || !isset($to['wikiID']))
		error(101);

	// check permission
	if (($permission = $group->getPermission($from['categoryID'])) < READ_ACCESS)
		error(19);

	$this->setTitle('Wiki Diff');
	$this->setTab('resources');

	// perfom diff
	$diff = &new Text_Diff(str_split($from['body']), str_split($to['body']));
	$renderer = &new Text_Diff_Renderer_inline();

	$this->setVar(array(
			'WIKI_ID'          => $from['wikiID'],
			'CATEGORY_ID'      => $from['categoryID'],
			'FROM_ID'          => $from['revisionID'],
			'TO_ID'            => $to['revisionID'],
			'HEADING'          => scrubText($from['heading']),
			'DIFF'             => $renderer->render($diff),
			'MODIFIED'         => outputDate(Cfg::get('date_long'), $from['modified']),
			'AUTHOR'           => formatName($from),
			'AUTHOR_ID'        => $from['authorID'],
			'CURRENT_REVISION' => $from['revisionID'],
			'LOCKED'           => $from['locked'] == 'true' ? '(Locked)' : '',
			'LOCK_UNLOCK'      => $from['locked'] == 'true' ? 'Unlock' : 'Lock',
	));

	// output revisions
	$revisionList = $wiki->getRevisionList($from['wikiID']);
	if (is_array($revisionList)) {
		$i = 0;
		foreach ($revisionList as $revision) {
			$i++;

			$output = array(
					'WIKI_ID'           => $from['wikiID'],
					'REVISION_ID'       => $from['revisionID'],
					'REVISION_COMPARE'  => $revision['revisionID'],
					'REVISION_DATE'     => outputDate(Cfg::get('date_notime'), $revision['created']),
					'REVISION_HEADING'	=> scrubText($revision['heading']),
					'REVISION_AUTHOR'	=> formatName($revision),
			);

			if($revision['revisionID']==$from['revisionID'])
				$output['REVISION_CURRENT'] = 'revisionCurrent';
			else if($revision['revisionID']==$to['revisionID'])
				$output['REVISION_CURRENT'] = 'revisionCompare';
			else
				$output['REVISION_CURRENT'] = '';

			$this->setVar($output);



			if ($from['revisionID'] == $revision['revisionID']) {
				$this->setVar('revision_comparison_block');
			} else {
				$this->parse('revision_comparison_block', 'revision_comparison');
			}

			$this->parse('revision_list_block', 'revision_list', true);

			if ($i == 1)
				$finalRevision = $revision['revisionID'];
		}
	} else
		$this->setVar('revision_list_block', '');
} else {
	error(2);
}

?>
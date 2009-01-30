<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$wikiHandler = new Wiki;
$groupHandler = new Group(HOMEPAGE_SERVICE);


if (is_numeric($this->arg[0])) {
	
	$this->setFile(TEMPLATE_content, 'wiki.html');
	$this->setBlock(TEMPLATE_content, 'revision_list', 'revision_list_block');
	
	$wiki = $wikiHandler->getByID($this->arg[0], $this->arg[1]);

	// check permission
	if ($groupHandler->getPermission($wiki['categoryID']) < READ_ACCESS)
		error(19);	

	$output = array('WIKI_ID'		=> $wiki['wikiID'],
					'CATEGORY_ID'	=> $wiki['categoryID'],
					'HEADING'		=> scrubText($wiki['heading']),
					'BODY'			=> scrubHTML($wiki['body']),
					'MODIFIED'		=> outputDate(DATE_long_format, $wiki['modified']),
					'MODIFIED_BY'	=> formatName($wiki),	
					);
	$this->setVar($output);
	
	// output revisions
	$revisionList = $wikiHandler->getRevisionList($wiki['wikiID']);
	if (is_array($revisionList)) {
		foreach ($revisionList as $revision) {
			$output = array('REVISION_ID'		=> $revision['revisionID'],
							'REVISION_DATE'		=> outputDate(DATE_long_format, $revision['created']),
							'REVISION_HEADING'	=> scrubText($revision['heading']),
							'REVISION_AUTHOR'	=> formatName($revision),
							);
			$this->setVar($output);
			$this->parse('revision_list_block', 'revision_list', true);
		}
	} else
		$this->setVar('revision_list_block', '');

} else
	error(2);
?>
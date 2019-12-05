<?php

use Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Breadcrumbs;

$wiki  = new Wiki();
$group = new Group(UserAccess::SERVICE_HOMEPAGE);
$userHandler = new User();

if (is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'wiki/diff.html');
	$this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');
	$this->setBlock(TEMPLATE_content, 'delete_link', 'delete_link_block');
	$this->setBlock(TEMPLATE_content, 'revert_link', 'revert_link_block');
	$this->setBlock(TEMPLATE_content, 'lock_link', 'lock_link_block');
	$this->setBlock(TEMPLATE_content, 'revision_list', 'revision_list_block');
    $this->setBlock(TEMPLATE_content, 'wiki_locked', 'wiki_locked_block');
	$this->setBlock('revision_list', 'revision_comparison', 'revision_comparison_block');

	$from = $wiki->getByID($this->arg[0], $this->arg[1]);
	$to   = $wiki->getByID($this->arg[0], $this->arg[2]);

	if (!isset($from['wikiID']) || !isset($to['wikiID']))
		error(101);

	try{
		$folder = FolderMapper::getInstance()->findById($from['categoryID']);
	} catch (\Schoolbox\Mapper\Exception $e) {
		error(2);
	}

	// check permission
	if (($permission = $group->getPermission($from['categoryID'])) < UserAccess::ACCESS_READ)
		error(19);

	$this->setTitle('Wiki Diff');
	$this->setTab('resources');

	// perfom diff
    $htmldiffConfig = new Caxy\HtmlDiff\HtmlDiffConfig();
    $htmldiffConfig->setPurifierCacheLocation(Config::get('xml_dir') . '/../purifier/');
    $htmldiffConfig->setEncoding('UTF-8');
    $diff = new Caxy\HtmlDiff\HtmlDiff($from['body'], $to['body']);
    $diff->setConfig($htmldiffConfig);
    $diff->build();

	$modifiedDate = new Alaress_Date_Format($from['modified'], 'U');

    $slug = Scrub::text($from['slug']);

	$this->setVar(array(
			'WIKI_ID'          => $from['wikiID'],
			'CATEGORY_ID'      => $from['categoryID'],
			'FOLDER_NAME'	   => Scrub::text($folder->getName()),
			'FROM_ID'          => $from['revisionID'],
			'TO_ID'            => $to['revisionID'],
			'HEADING'          => Scrub::text($from['heading']),
            'SLUG'             => $slug,
			'DIFF'             => $diff->getDifference(),
			'MODIFIED'         => $modifiedDate->getLong(),
			'AUTHOR'           => $userHandler->formatName($from),
			'AUTHOR_ID'        => $from['authorID'],
			'CURRENT_REVISION' => $from['revisionID'],
	));

    if ($from['locked'] == 'true') {
        $this->parse('wiki_locked_block', 'wiki_locked');
    }
    else {
        $this->setVar(array(
            'wiki_locked_block' => '',
        ));
    }

	// output revisions
	$revisionList = $wiki->getRevisionList($from['wikiID']);
	if (is_array($revisionList)) {
		$i = 0;
		foreach ($revisionList as $revision) {
			$i++;

			$revisionDate = new Alaress_Date_Format($revision['created'], 'U');

			$output = array(
					'WIKI_ID'           => $from['wikiID'],
					'REVISION_ID'       => $from['revisionID'],
					'REVISION_COMPARE'  => $revision['revisionID'],
					'REVISION_DATE'     => $revisionDate->getDateOnly(),
					'REVISION_HEADING'	=> Scrub::text($revision['heading']),
					'REVISION_AUTHOR'	=> $userHandler->formatName($revision),
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
	}
    else {
		$this->setVar('revision_list_block', '');
    }

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
            'href' => "/wiki/{$folder->getId()}/$slug",
            'class' => '',
        ],
        [
            'text' => 'Compare',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
else {
	error(2);
}

<?php

$this->setBlock($componentHandle, 'wiki_row', 'wiki_row_block');
$this->setBlock('wiki_row', 'modify_wiki', 'modify_wiki_block');

/* Custom sorting options
 */
$sortOptions = array('Heading', 'Created', 'Modified');

$wikiHandler = new Wiki();
$userHandler = new User();

$wikiList = $wikiHandler->getByCategory($categoryID, strtolower($config['sort']), $config['sortdir'], $config['size']);

if (is_array($wikiList)) {
	foreach ($wikiList as $wiki) {
		$modifiedTime = new Alaress_Date_Relative($wiki['modified']);

		$this->setVar(array(
			'WIKI_ID' => $wiki['wikiID'],
			'WIKI_HEADING' => Scrub::text($wiki['heading']),
			'WIKI_AUTHOR' => $userHandler->formatName($wiki),
			'WIKI_AUTHOR_ID' => $wiki['userID'],
			'WIKI_MODIFIED' => $modifiedTime->getRelativeTime(),
			'ON_OFF' => ++$j % 2 ? '' : 'dark',
		));

		$this->setVar('modify_wiki_block', '');

		if (($permission >= READ_ACCESS && $wiki['locked'] == 'false') || $permission >= WRITE_ACCESS) {
			$this->parse('modify_wiki_block', 'modify_wiki');
		}

		$this->parse('wiki_row_block', 'wiki_row', true);
	}
}
else {
	$this->setVar('wiki_row_block', '');
}


//setup block for submission button
$this->setBlock($componentHandle, 'submit_wiki', 'submit_wiki_block');

if ($permission < WRITE_ACCESS) {
	$this->setVar('submit_wiki_block', '');
}
else {
	$this->parse('submit_wiki_block', 'submit_wiki');
}

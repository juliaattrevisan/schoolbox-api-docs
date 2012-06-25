<?php
$this->setBlock($componentHandle, 'wiki_row', 'wiki_row_block');
$this->setBlock($componentHandle, 'submit_wiki', 'submit_wiki_block');
$this->setBlock('wiki_row', 'modify_wiki', 'modify_wiki_block');

/* Custom sorting options
 */
$sortOptions = array('Heading', 'Created', 'Modified');

$wikiHandler = new Wiki();
$userHandler = new User();

/* Set up component name
 */
$this->setVar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Wiki',

	/* These are for the Select Alternate Source resourcebrowser
	 */
	'COMPONENT_CONFIG_SRC_ID' => $config['homepage-wiki'],
	'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
	'COMPONENT_HANDLE' => strtolower($componentHandle),
));

/* Prevent multiple blog entries from other categories stacking up in the wrong place
 * and initialise empty blog roll by default
 */
$this->setVar('wiki_row_block', '');

/* Select news feed from alternate source, if specified
 */
$alternateCategoryId = false;
$alternatePermissions = 0;

/* If you chose an alternate source, check the permissions on it
 */
if (strlen($config['homepage-wiki'])) {
	$matches = array();
	preg_match('#/homepage/(\d+)#', $config['homepage-wiki'], $matches);

	if (count($matches)) {
		$alternatePermissions = $groupHandler->getPermission($matches[1]);

		if ($alternatePermissions >= READ_ACCESS) {
			$alternateCategoryId = $matches[1];
		}
	}
}

/* Load blog from some other homepage
 */
if ($alternateCategoryId) {
	/* Dodge around addComponent when config for this instance is empty
	 */
	if (is_array($config) && count($config)) {
		$wikiList = $wikiHandler->getByCategory($alternateCategoryId, strtolower($config['sort']), $config['sortdir'], $config['size']);
	}
	else {
		$wikiList = $wikiHandler->getByCategory($alternateCategoryId);
	}

	/* Controls such as Post News
	 */
	if ($alternatePermissions < WRITE_ACCESS) {
		$this->setVar("submit_wiki_block", "");
	}
	else {
		$this->setVar(array(
			'WIKI_CATEGORY_ID' => $alternateCategoryId
		));

		$this->parse("submit_wiki_block", "submit_wiki");
	}
}
/* Tried to load from alternate source but failed
 */
else if (strlen($config['homepage-wiki']) && !$alternateCategoryId) {
	$wikiList = array();

	/* Have update form to back to this homepage, not the wiki's homepage
	 */
	$this->setVar(array(
		'WIKI_CATEGORY_ID' => $categoryID
	));

	$this->setVar("submit_wiki_block", "");
}
/* Load blogs for the current homepage
 */
else if (!strlen($config['homepage-blog'])){
	/* Dodge around addComponent when config for this instance is empty
	 */
	if (is_array($config) && count($config)) {
		$wikiList = $wikiHandler->getByCategory($categoryID, strtolower($config['sort']), $config['sortdir'], $config['size']);
	}
	else {
		$wikiList = $wikiHandler->getByCategory($categoryID);
	}

	/* Have update form to back to this homepage, not the wiki's homepage
	 */
	$this->setVar(array(
		'WIKI_CATEGORY_ID' => $categoryID
	));

	/* Controls such as Post News
	 */
	if ($permission < WRITE_ACCESS) {
		$this->setVar("submit_wiki_block", "");
	}
	else {
		$this->parse("submit_wiki_block", "submit_wiki");
	}
}
/* Default
 */
else {
	$blogs = array();

	$this->setVar("submit_wiki_block", "");
}

/* Iterate
 */
if (is_array($wikiList)) {
	foreach ($wikiList as $wiki) {
		$modifiedTime = new Alaress_Date_Relative($wiki['modified']);

		$this->setVar(array(
			'WIKI_ID' => $wiki['wikiID'],
			'WIKI_CATEGORYID' => $wiki['categoryID'],
			'WIKI_SLUG' => $wiki['slug'],
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
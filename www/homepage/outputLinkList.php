<?php
//link output
$this->setBlock($componentHandle, 'link', 'link_block');
$this->setBlock('link', 'modify_link', 'modify_link_block');
$this->setBlock('link', 'show_posted_by', 'show_posted_by_block');

$linkHandler = new Link();
$linkList = $linkHandler->getAllInCategory($categoryID, $config);

/* Set up component name
 */
$this->setvar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Links'
));

/* Tick for checkbox which controls whether to show the Posted By details
 */
if ($config['compactMode']) {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => 'compact-mode',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
	));
}
else {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => '',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
	));
}

if (is_array($linkList)) {
	$userHandler = new User();

	foreach ($linkList as $link) {
		$url = new Url($link['url']);

		if ($url->isLocalUrl()) {
			$linkUrl = $url->getRelativeURL();
			$linkTarget = '';
		}
		else {
			$linkUrl = $url->getBase64EncodedRedirect();
			$linkTarget = 'external';
		}

		$date = new Alaress_Date_Format($link['submitted'], 'U');

		$output = array(
			'LINK_ID' => $link['id'],
			'LINK_NAME' => Scrub::text($link['description']),
			'LINK_URL' => $linkUrl,
			'LINK_TARGET' => $linkTarget,
			'LINK_POSTED_BY' => $userHandler->formatName($link),
			'LINK_POSTED_BY_ID' => $link['owner'],
			'LINK_POSTED_DATE' => $date->getShort(),
			'LINK_COMMENT' => nl2br(Scrub::text($link['comment'])),
			'ON_OFF' => ++$j%2? '' : 'dark',
			'HIDDEN'  => $link['hidden'] ? 'hidden' : '',
		);

		$this->setVar($output);

		/* If the config option is set to show Posted By information
		 */
		if ($config['show_posted_by']) {
			$this->parse('show_posted_by_block', 'show_posted_by');
		}
		else {
			$this->setVar('show_posted_by_block', '');
		}

		/* Controls
		 */
		if ($permission < WRITE_ACCESS) {
			$this->setVar('modify_link_block', '');
		}
		else {
			$this->parse('modify_link_block', 'modify_link');
		}

		$this->parse("link_block", "link", true);
	}
}
else {
	$this->setVar("link_block", "");
}

//setup block for submission button
$this->setBlock($componentHandle, "submit_link", "submit_link_block");

if ($permission < WRITE_ACCESS) {
	$this->setVar("submit_link_block", "");
}
else {
	$this->parse("submit_link_block", "submit_link");
}


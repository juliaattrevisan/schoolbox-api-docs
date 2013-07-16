<?php

$this->setFile(TEMPLATE_content, 'cms/myLinks.html');
$this->setBlock(TEMPLATE_content, 'myLink', 'myLink_block');

$myLinksHandler = new MyLinks();

$allLinks = $myLinksHandler->getAllByField('owner', $_SESSION['id']);

if (is_array($allLinks)) {
	foreach($allLinks as $currentLink) {
		$this->setVar(array(
			'MYLINK_URL' => $currentLink['url'],
			'MYLINK_DESCRIPTION' => $currentLink['description'],
			'MYLINK_ID' => $currentLink['id'],
		));

		$this->parse('myLink_block', 'myLink', true);
	}
}
else {
	$this->setVar('myLink_block', '');
}

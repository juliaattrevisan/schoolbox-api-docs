<?php
$this->setBlock($componentHandle, 'has_clickview', 'has_clickview_block');

/* If no url is specified it might time out -_-
 * So just hide all this instead
 */
if (strlen(trim($config['url']))) {
	$this->setVar(array(
		'URL' => $config['url']
	));

	$this->parse('has_clickview_block', 'has_clickview');
}
else {
	$this->setVar('has_clickview_block', '');
}
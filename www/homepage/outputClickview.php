<?php
$this->setBlock($componentHandle, 'has_clickview', 'has_clickview_block');

/* If no url is specified it might time out -_-
 * So just hide all this instead
 */
if (strlen(trim($config['url']))) {

	/* Pattern match the necessary code out of a url given to us
	 * by the Clickview web interface
	 *
	 * http://webplayer.clickview.com.au/?p=kwRu&v1=home.clickview.com.au
	 */
	$pattern = '/\?p=([a-zA-Z0-9]+)&/';

	$matches = array();

	if (preg_match($pattern, $config['url'], $matches)) {
		$this->setVar(array(
			'URL' => $matches[1],
		));

		$this->parse('has_clickview_block', 'has_clickview');
	}
	else {
		$this->setVar('has_clickview_block', '');
	}
}
else {
	$this->setVar('has_clickview_block', '');
}
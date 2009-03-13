<?php

$this->setBlock($componentHandle, "wiki_row", "wiki_row_block");

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/wiki.php';
$wikiHandler = new Wiki; 

$wikiList = $wikiHandler->getByCategory($categoryID);

if (is_array($wikiList)) {
	foreach ($wikiList as $wiki) {
		$output = array('WIKI_ID'	=> $wiki['wikiID'],
						'WIKI_HEADING'	=> scrubText($wiki['heading']),
						'WIKI_AUTHOR'	=> formatName($wiki),
						'WIKI_AUTHOR_ID'	=> $wiki['userID'],
						'WIKI_MODIFIED'	=> outputDate(DATE_short_format, $wiki['modified']),
						'ON_OFF' => ++$j%2? '' : 'dark',
						);
		$this->setVar($output);
		$this->parse('wiki_row_block', 'wiki_row', true);
	}
} else
	$this->setVar('wiki_row_block', '');

		
?>
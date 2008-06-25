<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/poll.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;
$page->setFile(TEMPLATE_body, "poll/poll-xml.html");
$page->setBlock(TEMPLATE_body, "series", "series_block");
$page->setBlock(TEMPLATE_body, "graph", "graph_block");

$pollHandler = new Poll;
$pollData = $pollHandler->getData($_GET['pollID']);

$colour = array('#ff0000',
				'#ff9900',
				'#ffff00',
				'#ccff00',
				'#00ff99',
				'#00ffff',
				'#0099ff',
				'#0066ff',
				'#9900ff',
				'#ff00ff',
				);

if (is_array($pollData)) {
	$pollData = array_reverse($pollData);
	$i = 0;
	foreach ($pollData as $data) {
		$output = array('XID'		=> $i, 
						'SERIES'	=> htmlspecialchars($data['option']),
						'COLOUR'	=> $colour[$i],
						'VALUE'		=> htmlspecialchars($data['result']),
						);
		$i++;
		$page->setVar($output);
		
		$page->parse("series_block", "series", true);
		$page->parse("graph_block", "graph", true);
	}
} else {
	$page->setVar("series_block", "");
	$page->setVar("graph_block", "");
}


header("Content-type: text/xml; charset=\"utf-8\"");
$page->outputPage(true);
?>
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/news.php';

$page = new SitePage(false);
$newsHandler = new News;

$page->setFile(TEMPLATE_body, 'feed/rss.xml');
$page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

$output = array('TITLE'			=> HEADING . ' - News',
				'DESCRIPTION'	=> 'Recent news items from the portal',
				'DATE'			=> date('r'),
				'LINK'			=> SITE_address . '/news/',
				);
$page->setVar($output);

$newsList = $newsHandler->getAllByGroup(unserialize(STUDENTGROUPIDS), array(1,2,3));

if (is_array($newsList)) {
	foreach ($newsList as $news) {
		$output = array('ITEM_TITLE'		=> $news['item_heading'],
						'ITEM_DESCRIPTION'	=> "<![CDATA[" . $news['item_body'] ."]]>",
						'ITEM_DATE'			=> outputDate('r', $news['item_datetime_posted']),
						'ITEM_LINK'			=> SITE_address . '/news/#' . $news['item_id'],
						);
		$page->setVar($output);
		$page->parse('rss_item_block', 'rss_item', true);
	}
} else
	$page->setVar('rss_item_block', '');
	
header('Content-type: application/rss+xml');
$page->outputPage(true);

exit;

?>
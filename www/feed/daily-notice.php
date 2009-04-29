<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/news.php';

$page = new SitePage(false);
$newsHandler = new News;

$page->setFile(TEMPLATE_body, 'feed/rss.xml');
$page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

$output = array('TITLE'			=> HEADING . ' - Daily Notices',
				'DESCRIPTION'	=> '',
				'DATE'			=> date('r'),
				'LINK'			=> SITE_address . '/news/',
				);
$page->setVar($output);

$newsList = $newsHandler->getDailyNotice(false);
if (is_array($newsList)) {
	foreach ($newsList as $news) {
		$output = array('ITEM_TITLE'		=> scrubText($news['item_heading']),
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

?>
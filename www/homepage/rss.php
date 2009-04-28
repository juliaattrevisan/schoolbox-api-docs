<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/feed.php';

$categoryHandler = new Category;
$feedHandler = new Feed;

if(is_numeric($this->arg[0])) {
	
	$category = $categoryHandler->getByID($this->arg[0]);
	
	header('Content-type: application/rss+xml; charset=utf-8');
	
	$this->setFile(TEMPLATE_body, 'homepage/rss.html');
	$this->setBlock(TEMPLATE_body, 'feed', 'feed_block');
	
	$output = array('HOMEPAGE_NAME' => $category['name'],
					'SITE_DOMAIN'	=> $_SERVER['HTTP_HOST'],
					'HOMEPAGE_URL'	=> '/homepage/'.$category['id'],
					'BUILD_DATE'	=> date('r'),
					);
					
	$this->setVar($output);

	$feedList = $feedHandler->getFeed($category['id'], 15);
	
	if(is_array($feedList)) {
		foreach($feedList as $feed) {
			$output = array('TITLE'	=> $feed['message'],
							'UPLOADED_DATE' => date('r',$feed['date']),
							);
							
			$this->setVar($output);
			$this->parse('feed_block', 'feed', true);
		}
	}
	$this->outputPage(true);
	
	exit;
}


?>
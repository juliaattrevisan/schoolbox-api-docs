<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/../include/Bootstrap.php';

$categoryHandler = new Category();
$feedHandler = new Feed();

$args = explode('/',$_GET['page']);

$catID = isset($args[2]) ? $args[2] : '';
$token = isset($args[3]) && !empty($args[3]) ? $args[3] : '';

$db = DeprecatedDatabase::getInstance();
$token = $db->getOne("SELECT `key` FROM `user` WHERE `key` = '$token'");

if(is_numeric($catID) && !empty($token)) {

    $page = new SitePage(false);
	
	$category = $categoryHandler->getByID($catID);

    if(!$category) {
        die();
    }
	
	header('Content-type: application/rss+xml; charset=utf-8');
	
	$page->setFile(TEMPLATE_body, 'homepage/rss.html');
	$page->setBlock(TEMPLATE_body, 'feed', 'feed_block');
	
	$output = array(
        'HOMEPAGE_NAME' => $category['name'],
		'SITE_DOMAIN'	=> $_SERVER['HTTP_HOST'],
        'HOMEPAGE_URL'	=> 'homepage/'.$category['id'],
		'BUILD_DATE'	=> date('r'),
	);

    $page->setVar($output);

	$feedList = $feedHandler->getFeed($category['id'], 15);
	
	if(is_array($feedList)) {
		foreach($feedList as $feed) {
			$output = array(
                'TITLE'	=> $feed['message'],
				'UPLOADED_DATE' => date('r',$feed['date']),
            );

            $page->setVar($output);
            $page->parse('feed_block', 'feed', true);
		}
	}
    else {
        $page->setVar('feed_block', '');
    }
    $page->outputPage(true);
	
	exit;
}


?>
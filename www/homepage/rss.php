<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$categoryHandler = new Category();
$feedHandler = new Feed();

// trim in case the leading slash is captured in the URI
$page = trim($_GET['page'], '/');
$args = explode('/', $page);

$catID = isset($args[2]) ? $args[2] : '';
$token = isset($args[3]) && !empty($args[3]) ? $args[3] : '';

$db = DeprecatedDatabase::getInstance();
$token = $db->getOne("SELECT `key` FROM `user` WHERE `key` = '$token'");

if (is_numeric($catID) && !empty($token)) {

    $page = new SitePage(false);

    $category = $categoryHandler->getByID($catID);

    if (!$category) {
        die();
    }

    header('Content-type: application/rss+xml; charset=utf-8');

    $page->setFile(TEMPLATE_body, 'homepage/rss.html');
    $page->setBlock(TEMPLATE_body, 'feed', 'feed_block');

    $output = array(
        'HOMEPAGE_NAME' => Scrub::xml($category['name']),
        'SITE_DOMAIN'   => Scrub::xml($_SERVER['HTTP_HOST']),
        'HOMEPAGE_URL'  => Scrub::xml('homepage/' . $category['id']),
        'BUILD_DATE'    => Scrub::xml(date('r')),
    );

    $page->setVar($output);

    $feedList = $feedHandler->getFeed($category['id'], 15);

    if (is_array($feedList) && count($feedList)) {
        foreach ($feedList as $feed) {
            $output = array(
                'TITLE'         => Scrub::xml($feed['message']),
                'UPLOADED_DATE' => Scrub::xml(date('r', $feed['date'])),
            );

            $page->setVar($output);
            $page->parse('feed_block', 'feed', true);
        }
    } else {
        $page->setVar('feed_block', '');
    }
    $page->outputPage(true);

    exit;
}

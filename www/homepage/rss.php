<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

// trim in case the leading slash is captured in the URI
$page = trim($_GET['page'], '/');
$args = explode('/', $page);

$catID = isset($args[2]) ? $args[2] : '';
$token = isset($args[3]) && !empty($args[3]) ? $args[3] : '';

$db = Database::getInstance();

$stmt = $db->prepare("
    SELECT `key`, `group_type`
    FROM `user`
    LEFT JOIN `groups` ON `user`.`group_id` = `groups`.`id`
    WHERE `key` = :key
");

$stmt->execute(array('key' => $token));

if ($stmt->rowCount()) {
    list($token, $roletype) = $stmt->fetch(PDO::FETCH_NUM);
} else {
    die('No RSS available');
}

if (is_numeric($catID) && !empty($token)) {

    $page = new SitePage(false);

    try {
        $category = \Schoolbox\Resource\CategoryMapper::getInstance()->findById($catID);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(13);
        exit;
    }

    header('Content-type: application/rss+xml; charset=utf-8');

    $page->setFile(TEMPLATE_body, 'homepage/rss.html');
    $page->setBlock(TEMPLATE_body, 'feed', 'feed_block');

    $output = array(
        'HOMEPAGE_NAME' => Scrub::xml($category->getName()),
        'SITE_DOMAIN'   => Scrub::xml($_SERVER['HTTP_HOST']),
        'HOMEPAGE_URL'  => Scrub::xml('homepage/' . $category->getId()),
        'BUILD_DATE'    => Scrub::xml(date('r')),
    );

    $page->setVar($output);

    $feed = new \Schoolbox\Feed\StandardFeed(new \Schoolbox\Config\Loader(), $category->getId(), $roletype);

    foreach ($feed->renderAsFeedArray() as $item) {
        $output = array(
            'TITLE'         => Scrub::xml(Scrub::html2text($item['title'])),
            'UPLOADED_DATE' => Scrub::xml(date('r', $item['time'])),
        );

        $page->setVar($output);
        $page->parse('feed_block', 'feed', true);
    }

    if ($feed->count() == 0) {
        $page->setVar('feed_block', '');
    }

    $page->outputPage(true);

    exit;
}

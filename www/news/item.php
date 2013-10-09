<?php
/**
 * News Item view
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// specified an article ID?
if (!is_numeric($this->arg[0])) {
    // get the most recent one, and redirect
    $newsHandler = new News();
    $allNews = $newsHandler->getNewsListing(false);
    $itemID = $allNews[0]['item_id'];
    if ($itemID) {
        self::localRedir("/news/item/$itemID");
    } else {
        self::localRedir("/news");
    }
}

$this->setFile(TEMPLATE_content, 'news/item.html');
$this->setBlock(TEMPLATE_content, 'featured_image', 'featured_image_block');
$this->setBlock(TEMPLATE_content, 'modify_news', 'modify_news_block');
$this->setBlock(TEMPLATE_content, 'add_news', 'add_news_block');
$this->setBlock(TEMPLATE_content, 'topic_list', 'topic_list_block');
$this->setBlock(TEMPLATE_content, 'previous_article', 'previous_article_block');
$this->setBlock(TEMPLATE_content, 'next_article', 'next_article_block');

$this->setTab('news');

$groupHandler = new Group(NEWS_SERVICE);
$userHandler = new User();
$newsHandler = new News();
$article = null;

if (!($article = $newsHandler->getByID($this->arg[0]))) {
    error(19);
}

$topic = $newsHandler->getTopicByID($article['item_topic_id']);

$url = new Url($topic['name']);

$user = $userHandler->getByID($article['item_owner']);
$fullname = $userHandler->formatName($user);

$d = new Alaress_Date_Format($article['item_datetime_posted'], 'y-m-d');
$e = new Alaress_Date_Format($article['item_datetime_expires'], 'y-m-d');

$output = array(
    'NEWS_ID' => $article['item_id'],
    'NEWS_TITLE' => $article['item_heading'],
    'NEWS_BODY' => Scrub::html($article['item_body']),
    'NEWS_IMAGE' => $article['item_image'],
    'POSTER' => $fullname,
    'POSTER_EXTERNAL_ID' => $user['synergy_id'],
    'POSTER_ID' => $article['item_owner'],
    'TOPIC_NAME' => Scrub::text($topic['name']),
    'TOPIC_CLEAN' => $url->getSlug(),
    'DATETIME' => $d->getLong(),
    'EXPIRY' => $_SESSION['su'] == 1 ? ' - ' . $e->getLong() : '',
);

$this->setVar($output);

// is there an image to output
if ($article['item_image']) {
    $this->parse('featured_image_block', 'featured_image');
} else {
    $this->setVar('featured_image_block', '');
}

// does the current user have permission to modify this news article?
$this->setVar('add_news_block', '');
$this->setVar('modify_news_block', '');
if (($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS) {
    $this->parse('add_news_block', 'add_news');
    if ($article['item_owner'] == $_SESSION['id'] || $permission == FULL_ACCESS) {
        $this->parse('modify_news_block', 'modify_news');
    }
}

$now = new Alaress_Date_Format(time(), 'U');
$articleExpiry = new Alaress_Date_Format($article['item_datetime_expires'], 'y-m-d');
$expired = $articleExpiry->getUnixTimestamp() < $now->getUnixTimestamp();

// generate news and topic lists
$topicList = $newsHandler->getTopicList();
array_unshift($topicList, array('name' => 'All'));

// output the list of topics for the sidebar
foreach ($topicList as $sidebarTopic) {
    $topicUrl = new Url($sidebarTopic['name']);
    $output = array(
        'LIST_TOPIC_NAME' => $sidebarTopic['name'],
        'LIST_TOPIC_CLEAN' => $topicUrl->getSlug(),
        'LIST_TOPIC_SELECTED' => $topicUrl->getSlug() == $url->getSlug() ? 'current' : '',
    );
    $this->setVar($output);
    $this->parse('topic_list_block', 'topic_list', true);
}

// parse previous and next articles
$prevNext = array();
$current = -1;
$allNews = $newsHandler->getNewsListing($expired, $topic['name']);
if($allNews != false) {
	foreach ($allNews as $newsItem) {
	    $prevNext[] = array(
	        $newsItem['item_id'],
	        $newsItem['item_heading']
	    );
	    // current article in the list?
	    if ($newsItem['item_id'] == $article['item_id']) {
	        $current = count($prevNext) - 1;
	    }
	}
}

// setup "objects" for output
$prev = $current > 0 ? $prevNext[$current - 1] : null;
$next = $current < count($prevNext) - 1 ? $prevNext[$current + 1] : null;

// output previous
if ($prev) {
    $this->setVar('PREVIOUS_ARTICLE_ID', $prev[0]);
    $this->setVar('PREVIOUS_ARTICLE_TITLE', $prev[1]);
    $this->parse('previous_article_block', 'previous_article', false);
} else {
    $this->setVar('previous_article_block', '');
}

// output next
if ($next) {
    $this->setVar('NEXT_ARTICLE_ID', $next[0]);
    $this->setVar('NEXT_ARTICLE_TITLE', $next[1]);
    $this->parse('next_article_block', 'next_article', false);
} else {
    $this->setVar('next_article_block', '');
}


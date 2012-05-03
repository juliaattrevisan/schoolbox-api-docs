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
if(!is_numeric($this->arg[0])) {
    // get the most recent one, and redirect
    $newsHandler = new News();
    $allNews = $newsHandler->getNewsListing(false);
    $itemID = $allNews[0]['item_id'];
    if($itemID) {
        self::localRedir("/news/item/$itemID");
    }
    else {
        self::localRedir("/news");
    }
}

$this->setFile(TEMPLATE_content, 'news/item.html');
$this->setBlock(TEMPLATE_content, 'modify_news', 'modify_news_block');
$this->setBlock(TEMPLATE_content, 'topic_list', 'topic_list_block');
$this->setBlock('topic_list', 'article_list', 'article_list_block');
$this->setBlock('article_list', 'article_list_item', 'article_list_item_block');
$this->setBlock(TEMPLATE_content, 'previous_article', 'previous_article_block');
$this->setBlock(TEMPLATE_content, 'next_article', 'next_article_block');

$this->setTab('news');

$groupHandler = new Group();
$userHandler = new User();
$newsHandler = new News();
$article = null;

if(!($article = $newsHandler->getByID($this->arg[0]))) {
    error(19);
}

$topic = $newsHandler->getTopicByID($article['item_topic_id']);

$url = new Url($topic['name']);

$user = $userHandler->getByID($article['item_owner']);
$fullname = $userHandler->formatName($user);
$owner = "<a href=\"/search/user/{$article['item_owner']}\">$fullname</a>";

$d = new Alaress_Date_Format($article['item_datetime_posted'], 'y-m-d');
$e = new Alaress_Date_Format($article['item_datetime_expires'], 'y-m-d');

$output = array(
    'NEWS_ID'     => $article['item_id'],
    'NEWS_TITLE'  => $article['item_heading'],
    'POSTER'      => $owner,
    'DATETIME'    => $d->getLong(),
    'EXPIRY'      => $_SESSION['su'] == 1 ? ' - ' . $e->getLong() : '',
    'NEWS_BODY'   => Scrub::html($article['item_body']),
    'TOPIC_NAME'  => Scrub::text($topic['name']),
    'TOPIC_CLEAN' => $url->getSlug()
);

$this->setVar($output);

// does the current user have permission to modify this news article?
if (($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS)
    $this->parse('modify_news_block', 'modify_news');
else
    $this->setVar('modify_news_block', '');

$now = new Alaress_Date_Format(time(), 'U');
$articleExpiry = new Alaress_Date_Format($article['item_datetime_expires'], 'y-m-d');
$expired = $articleExpiry->getUnixTimestamp() < $now->getUnixTimestamp();

// generate news and topic lists
$topicList = $newsHandler->getTopicList();
$allNews = $newsHandler->getNewsListing($expired, $topic['name']);


// output the list of topics for the sidebar
foreach($topicList as $sidebarTopic) {
    $url = new Url($sidebarTopic['name']);
    $items = $newsHandler->getNewsListing($expired, $sidebarTopic['name']);
    $output = array(
        'LIST_TOPIC_NAME' => $sidebarTopic['name'],
        'LIST_TOPIC_CLEAN' => ($expired ? 'archive/' : '') . $url->getSlug(),
        'LIST_TOPIC_RECENT_ARTICLE_ID' => $items[0]['item_id'],
    );
    $this->setVar($output);

    $items = $newsHandler->getNewsListing($expired, $sidebarTopic['name']);

    if(is_array($items)) {
        foreach($items as $listItem) {

            $output = array(
                'LIST_TOPIC_ARTICLE_CURRENT' => ($listItem['item_id'] == $article['item_id']) ? 'current' : '',
                'LIST_TOPIC_ARTICLE_NAME'    =>  $listItem['item_heading'],
                'LIST_TOPIC_ARTICLE_ID'      =>  $listItem['item_id']
            );
            $this->setVar($output);
            $this->parse('article_list_item_block', 'article_list_item', true);
        }
        $this->parse('article_list_block', 'article_list', true);
        $this->parse('topic_list_block', 'topic_list', true);
    }
    else {
        $this->setVar('article_list_block', '');
    }
    $this->setVar('article_list_item_block', '');
    $this->setVar('article_list_block','');
}

// parse previous and next articles
$prevNext = array();
$current = -1;
foreach($allNews as $newsItem) {
    $prevNext[] = array(
        $newsItem['item_id'],
        $newsItem['item_heading']
    );
    // current article in the list?
    if($newsItem['item_id'] == $article['item_id']) {
        $current = count($prevNext) - 1;
    }
}

// setup "objects" for output
$prev = $current > 0 ? $prevNext[$current - 1] : null;
$next = $current < count($prevNext) - 1 ? $prevNext[$current + 1] : null;

// output previous
if($prev) {
    $this->setVar('PREVIOUS_ARTICLE_ID', $prev[0]);
    $this->setVar('PREVIOUS_ARTICLE_TITLE', $prev[1]);
    $this->parse('previous_article_block', 'previous_article', false);
} else {
    $this->setVar('previous_article_block', '');
}

// output next
if($next) {
    $this->setVar('NEXT_ARTICLE_ID', $next[0]);
    $this->setVar('NEXT_ARTICLE_TITLE', $next[1]);
    $this->parse('next_article_block', 'next_article', false);
} else {
    $this->setVar('next_article_block', '');
}


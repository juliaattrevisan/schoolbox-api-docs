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
    error(19);
}

$this->setFile(TEMPLATE_content, 'news/item.html');
$this->setBlock(TEMPLATE_content, 'modify_news', 'modify_news_block');
$this->setBlock(TEMPLATE_content, 'topic_list', 'topic_list_block');
$this->setBlock('topic_list', 'article_list', 'article_list_block');

$this->setTab('news');

$groupHandler = new Group();
$userHandler = new User();
$newsHandler = new News();
$article = null;

if(!($article = $newsHandler->getByID($this->arg[0]))) {
    error(0);
}

$topic = $newsHandler->getTopicByID($article['item_topic_id']);

$url = new Url($topic['name']);

$user = $userHandler->getByID($article['item_owner']);
$fullname = $userHandler->formatName($user);
$owner = "<a href=\"/search/user/{$article['item_owner']}\">$fullname</a>";

$output = array(
    'NEWS_ID'     => $article['item_id'],
    'NEWS_TITLE'  => $article['item_heading'],
    'POSTER'      => $owner,
    'DATETIME'    => $article['item_datetime_posted'],
    'EXPIRY'      => $_SESSION['su'] == 1 ? 'Expires: ' . $article['item_datetime_expires'] : '',
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
    $output = array(
        'LIST_TOPIC_NAME' => $sidebarTopic['name'],
        'LIST_TOPIC_CLEAN' => ($expired ? 'archive/' : '') . $url->getSlug(),
    );
    $this->setVar($output);


    // if the current sidebar topic matches the article, load the news results into that section
    if($sidebarTopic['newsTopicID'] == $article['item_topic_id']) {
        foreach($allNews as $listItem) {

            if($listItem['item_id'] == $article['item_id'])
                $this->setVar('LIST_TOPIC_ARTICLE', $listItem['item_heading']);
            else
                $this->setVar('LIST_TOPIC_ARTICLE', '<a href="/news/item/' . $listItem['item_id'] . '">' . $listItem['item_heading'] . '</a>');

            $this->parse('article_list_block', 'article_list', true);
        }
    } else {
        $this->setVar('article_list_block', '');
    }

    $this->parse('topic_list_block', 'topic_list', true);
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
    $this->setVar('PREVIOUS_ARTICLE', '<a href="/news/item/' . $prev[0] . '">&laquo; ' . $prev[1] . '</a>');
} else {
    $this->setVar('PREVIOUS_ARTICLE', '');
}

// output next
if($next) {
    $this->setVar('NEXT_ARTICLE', '<a class="next" href="/news/item/' . $next[0] . '">' . $next[1] . ' &raquo;</a>');
} else {
    $this->setVar('NEXT_ARTICLE', '');
}


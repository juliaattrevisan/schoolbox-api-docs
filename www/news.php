<?php
/**
 * News Archive
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 */

$this->setFile(TEMPLATE_content, 'news/news_list.html');
$this->setBlock(TEMPLATE_content, 'add_news', 'add_news_block');
$this->setBlock(TEMPLATE_content, 'has_news', 'has_news_block');
$this->setBlock(TEMPLATE_content, 'no_news', 'no_news_block');
$this->setBlock('has_news', 'news', 'news_block');
$this->setBlock('news', "news_image", "news_image_block");
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'topic_list', 'topic_list_block');

$this->setVar('topic_header_block', '');

$this->setTab('news');

$groupHandler = new Group(NEWS_SERVICE);
$newsHandler = new News();


if ($this->arg[0]) {
    $selectedTopic = $this->arg[0];
} else {
    $selectedTopic = 'all';
}

if (is_numeric($this->arg[1])) {
    $page = $this->arg[1];
} else {
    $page = 1;
}

$this->setTitle('News');
$this->setVar('PAGE_TITLE', 'News');


// display add news button if user is allowed to
if (($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS) {
    $this->parse('add_news_block', 'add_news');
} else {
    $this->setVar('add_news_block', '');
}

/* Create the feed url
 */
$urlString = "/feed/news-school-rss.php?user={$_SESSION['user']->getKey()}";

// generate news and topic lists
$topicMapper = new \Schoolbox\News\TopicMapper();
$topicList = $topicMapper->findAll(array('order'=>array('sequence'=>'ASC')));

$all = new \Schoolbox\News\Topic();
$all->setName('All');
$topicList->prepend($all);

$topicSlugMap = array();
// output the list of topics for the sidebar
foreach ($topicList as $sidebarTopic) {
    $output = array(
        'LIST_TOPIC_NAME' => Scrub::text($sidebarTopic->getName()),
        'LIST_TOPIC_CLEAN' => $sidebarTopic->getSlug(),
        'LIST_TOPIC_SELECTED' => $selectedTopic == $sidebarTopic->getSlug() ? 'active' : '',
    );
    $this->setVar($output);

    if($sidebarTopic->getName() != 'All' && $selectedTopic == $sidebarTopic->getSlug()) {
        $urlString .= "&topic={$sidebarTopic->getId()}";
    }

    $topicSlugMap[$sidebarTopic->getSlug()] = $sidebarTopic->getName();
    $this->parse('topic_list_block', 'topic_list', true);
}

$this->setVar('SCHOOL_NEWS_RSS_FEED_URL', $urlString);

// forcibly remap 'all' to blank
$topicSlugMap['all'] = '';

$visibleListSubset = $newsHandler->getNewsListing(false, $topicSlugMap[$selectedTopic]);

// setup pagination for display
$newsPerPage = Cfg::get('search_type_limit');
$totalPages = ceil(count($visibleListSubset) / $newsPerPage);
$newsIndex = $newsPerPage * ($page - 1);

// figure out which pagination buttons should be displayed
if ($page < 2) {
    $this->setVar('previous_button_block', '');
} else {
    $this->parse('previous_button_block', 'previous_button');
}

if ($page >= $totalPages) {
    $this->setVar('next_button_block', '');
} else {
    $this->parse('next_button_block', 'next_button');
}

// set pagination button destinations
$pageSlug = "${selectedTopic}/";

if ($totalPages > 1) {
    for ($j = 1; $j <= $totalPages; $j++) {
        if ($page == $j) {
            $this->setVar('CURRENT', 'active');
        } else {
            $this->setVar('CURRENT', '');
        }

        $this->setVar('PAGE_INDEX', $j);
        $this->setVar('PAGE_INDEX_REF', $pageSlug . $j);

        $this->parse('page_number_block', 'page_number', true);
    }
} else {
    $this->setVar('page_number_block', '');
}

$outputPagination = array(
    'PREVIOUS_PAGE' => $pageSlug . ($page - 1),
    'NEXT_PAGE' => $pageSlug . ($page + 1),
    'LAST_PAGE' => $pageSlug . $totalPages,
);

$this->setVar($outputPagination);

$items = $newsHandler->getNewsListing(false, $topicSlugMap[$selectedTopic], 0, 0, $newsIndex, $newsPerPage, true);
$this->setVar([
    'TOPIC_CLEAN' => $selectedTopic,
    'TOPIC_TITLE' => $topicSlugMap[$selectedTopic] ?: 'All'
]);

if (is_array($items)) {

    $i = 0;
    $total = count($items);
    $userHandler = new User();
    $j = 0;

    if ($items) {
        foreach ($items as $article) {

            $i++;

            /* The usual stuff
             */
            $fullname = $userHandler->formatName($article);
            $owner = "<a href=\"/search/user/{$article['uid']}\">$fullname</a>";

            $url = new Url($previous['topicName']);

            $formatter = new TextFormatter();

            $postDate = new Alaress_Date_Format($article['post_datetime'], 'U');

            $output = array(
                'NEWS_ID' => $article['item_id'],
                'NEWS_TITLE' => $article['item_heading'],
                'POSTER' => $owner,
                'DATETIME' => $postDate->getLong(),
                'NEWS_SHORT' => TextFormatter::cutSentence(Scrub::html2text($article['item_body']), 300),
                'NEWS_IMAGE' => $article['item_image'],
                'TOPIC_NAME' => Scrub::text($previous['topicName']),
                'ON_OFF'         => ++$j % 2 ? 'dark' : ''
            );
            $this->setVar($output);

            if ($article['item_image']) {
                $this->parse('news_image_block', 'news_image');
            } else {
                $this->setVar('news_image_block');
            }

            $this->parse('news_block', 'news',true);
        }

        $this->parse('has_news_block', 'has_news');
        $this->setVar(array(
            'no_news_block' => ''
        ));
    }
} else {
    $this->setVar(array(
        'has_news_block' => ''
    ));

    $this->parse('no_news_block', 'no_news');
}



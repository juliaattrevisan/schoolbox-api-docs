<?php
/**
 * News Item view
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;

// Check there's actually a news ID in the URL
if (!is_numeric($this->arg[0])) {
    $this->pageNotFound();
}

/* @var $user \Schoolbox\User */
$user = $_SESSION['user'];

$groupHandler = new Group(NEWS_SERVICE);
$newsHandler = new News();
$article = null;

if (!($article = $newsHandler->getByID($this->arg[0]))) {
    error(ErrorMapper::ERROR_POST_NOT_FOUND);
}

/* Check user has permission to see this news item
 */
$newsAccess = $user->getAccessList()->filterBy('service', UserAccess::SERVICE_NEWS);

// Check superuser
$isSuperuser = false;

if ($user->isSuperuser()) {
    $isSuperuser = true;
}

// Check if user is author or owner
$isOwner = false;
if ($user->getId() == $article['item_owner']) {
    $isOwner = true;
}

// Check if user has moderate access
$isModerator = false;
if (!empty($article['campusList'])) {
    foreach ($article['campusList'] as $articleCampusId) {
        foreach ($newsAccess as $access) {
            if ($access->getServiceIdentifier() == $articleCampusId) {
                $isModerator = true;
            }
        }
    }
}

// Check if user is in one of the selected roles
$hasRole = false;
if (!empty($article['groupList'])) {
    foreach ($article['groupList'] as $groupId) {
        if ($user->getRole()->getId() == $groupId) {
            $hasRole = true;
        }
    }
}

// Check user is in one of the selected campuses
$hasCampus = false;
if (!empty($article['campusList'])) {
    foreach ($article['campusList'] as $articleCampusId) {
        foreach ($user->getCampusCollection() as $userCampus) {
            if ($userCampus->getId() == $articleCampusId) {
                $hasCampus = true;
            }
        }
    }
}

// We'll say they have 'normalAccess' if they pass the role & campus test
$hasNormalAccess = false;
if ($hasRole && $hasCampus) {
    $hasNormalAccess = true;
}

// Check if user has been added individually for access to this
$hasIndividualAccess = false;
if (!empty($article['userList'])) {
    foreach ($article['userList'] as $accessUser) {
        if ($accessUser['id'] == $user->getId()) {
            $hasIndividualAccess = true;
        }
    }
}

// Error out if user didn't pass any of the checks
if (!$isSuperuser && !$isOwner && !$isModerator && !$hasNormalAccess && !$hasIndividualAccess) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$this->setFile(TEMPLATE_content, 'news/item.html');
$this->setBlock(TEMPLATE_content, 'featured_image', 'featured_image_block');
$this->setBlock(TEMPLATE_content, 'modify_news', 'modify_news_block');
$this->setBlock(TEMPLATE_content, 'add_news', 'add_news_block');
$this->setBlock(TEMPLATE_content, 'topic_list', 'topic_list_block');
$this->setBlock(TEMPLATE_content, 'previous_article', 'previous_article_block');
$this->setBlock(TEMPLATE_content, 'next_article', 'next_article_block');
$this->setBlock(TEMPLATE_content, 'poster_link_open', 'poster_link_open_block');
$this->setBlock(TEMPLATE_content, 'poster_link_close', 'poster_link_close_block');

$this->setTab('news');



$topic = $newsHandler->getTopicByID($article['item_topic_id']);

if (!$topic) {
    // Topic was deleted
    error(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
}

$url = new Url($topic['name'], '');

$owner = UserMapper::getInstance()->findByIdWithFallback($article['item_owner']);

// phplib weirdness - gets overridden if user is found to exist
$this->setVar('poster_link_open_block', '');
$this->setVar('poster_link_close_block', '');

$userId = $owner->getId();
if (isset($userId)) {
    $owner = UserMapper::getInstance()->getUnknownUser();
} else {
    $deletedAt = $owner->getDeletedAt();
    if ($deletedAt instanceof \Alaress_Date_NullDate) {
        // render a user profile link only if user hasn't been soft-deleted
        $this->parse('poster_link_open_block', 'poster_link_open');
        $this->parse('poster_link_close_block', 'poster_link_close');
    }
}

$d = new Alaress_Date_Format($article['item_datetime_published'], Alaress_Date_Format::DATABASE_FORMAT);
$e = new Alaress_Date_Format($article['item_datetime_expires'], Alaress_Date_Format::DATABASE_FORMAT);

$output = array(
    'NEWS_ID' => $article['item_id'],
    'NEWS_TITLE' => Scrub::text($article['item_heading']),
    'NEWS_BODY' => empty($article['item_body'])
        ? Scrub::html($article['item_blurb']) : Scrub::html($article['item_body']),
    'NEWS_IMAGE' => $article['item_image'],
    'POSTER' => $owner->getFullName(),
    'POSTER_EXTERNAL_ID' => $owner->getExternalId(),
    'POSTER_ID' => $owner->getId(),
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

// If expiry date has passed, mark news as expired
// If today is the expiry date though, don't mark it
$startOfDay        = (new Alaress_Date_Format())->startOfDay();
$articleExpiry = new Alaress_Date_Format($article['item_datetime_expires'], Alaress_Date_Format::DATABASE_FORMAT);
$expired       = $articleExpiry->lt($startOfDay);

// generate news and topic lists
$topicList = $newsHandler->getTopicList();
array_unshift($topicList, array('name' => 'All'));

// output the list of topics for the sidebar
foreach ($topicList as $sidebarTopic) {
    $topicUrl = new Url($sidebarTopic['name'], '');
    $output = array(
        'LIST_TOPIC_NAME' => Scrub::text($sidebarTopic['name']),
        'LIST_TOPIC_CLEAN' => $topicUrl->getSlug(),
        'LIST_TOPIC_SELECTED' => $topicUrl->getSlug() == $url->getSlug() ? 'active' : '',
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
    $this->setVar('PREVIOUS_ARTICLE_TITLE', Scrub::text($prev[1]));
    $this->parse('previous_article_block', 'previous_article', false);
} else {
    $this->setVar('previous_article_block', '');
}

// output next
if ($next) {
    $this->setVar('NEXT_ARTICLE_ID', $next[0]);
    $this->setVar('NEXT_ARTICLE_TITLE', Scrub::text($next[1]));
    $this->parse('next_article_block', 'next_article', false);
} else {
    $this->setVar('next_article_block', '');
}

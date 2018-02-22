<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

use Schoolbox\News\ArticleMapper;
use Schoolbox\News\TopicMapper;
use Schoolbox\News\Topic;

$page = new SitePage(false);

$user = null;
$topic = null;

/* Param explosion
 */
$params = parse_url($_SERVER['QUERY_STRING']);

preg_match('/user=([a-zA-Z0-9]+)(&topic=)?(\d*)/', $params['path'], $matches);

$userKey = $matches[1];
$topicId = $matches[3];

/* Find all the bits
 */
if (strlen($userKey)) {
    try {
        $userMapper = new \Schoolbox\UserMapper();
        $user = $userMapper->findOneByColumn('key', $userKey);
        $user = $userMapper->findById($user->getId());
    } catch (\Exception $e) {
        error(31);
    }

    /* Optional category filter
     */
    if (is_numeric($topicId)) {
        try {
            $newsTopicMapper = new TopicMapper();
            $newsTopic = $newsTopicMapper->findById($topicId);
        } catch (\Exception $e) {
            $newsTopic = new Topic;
        }
    }
    else {
        $newsTopic = new Topic;
    }

    /* Find the articles
     */
    $articleMapper = new ArticleMapper();
    $allNews = $articleMapper->findAllForUserAndTopic($user, $newsTopic);

    /* Render all this stuff
     */
    $page->setFile(TEMPLATE_body, 'feed/rss.xml');
    $page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

    $page->setVar(
        array(
            'TITLE' => Scrub::xml(Cfg::get('school_name') . ' - News'),
            'DESCRIPTION' => Scrub::xml('Recent news items from Schoolbox'),
            'DATE' => Scrub::xml(date('r')),
            'LINK' => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            'SELF' => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']),
        )
    );

    if ($allNews->count()) {
        $processedIds = array();

        /* Remove dupes
         */
        foreach ($allNews as $currentNews) {
            /** @var $currentNews Schoolbox\News\Article */
            if (!in_array($currentNews->getId(), $processedIds)) {

                /* Image detection (category news has no images)
                 */
                $imageStr = '';

                if (!is_null($currentNews->getImage())) {
                    $imageStr = "<img src=\"" . $page->conType(
                        ) . $_SERVER['HTTP_HOST'] . "/storage/image.php?hash={$currentNews->getImage()->getHash(
                        )}&size=constrain200\">";
                }

                $page->setVar(
                    array(
                        'ITEM_TITLE' => Scrub::xml($currentNews->getHeading()),
                        'ITEM_TOPIC' => Scrub::xml($currentNews->getTopic()->getName()),
                        'ITEM_DESCRIPTION' => "<![CDATA[" . Scrub::html($imageStr . $currentNews->getBody()) . "]]>",
                        'ITEM_DATE' => Scrub::xml($currentNews->getPublishDate()->getUsingFormat(datetime::RFC2822)),
                        'ITEM_LINK' => Scrub::xml(
                                $page->conType() . $_SERVER['HTTP_HOST'] . '/news/item/' . $currentNews->getId()
                            ),
                    )
                );

                $processedIds[] = $currentNews->getId();

                $page->parse('rss_item_block', 'rss_item', true);
            }
        }
    } else {
        $page->setVar('rss_item_block', '');
    }
} else {
    error(31);
}

header('Content-type: application/rss+xml; charset=UTF-8');
$page->outputPage(true);
exit();

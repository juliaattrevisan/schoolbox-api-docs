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
use Schoolbox\Resource\Category\NewsMapper;

$page = new SitePage(false);

$user = null;
$folder = null;

/* Param explosion
 */
$params = parse_url($_SERVER['QUERY_STRING']);

preg_match('/user=([a-zA-Z0-9]+)(&category=)?(\d*)/', $params['path'], $matches);

$userKey = $matches[1];
$folderId = $matches[3];

/* Find all the bits
 */
if (strlen($userKey)) {
    try {
        $userMapper = new \Schoolbox\UserMapper();
        $user = $userMapper->findOneByColumn('key', $userKey);
    } catch (\Exception $e) {
        error(31);
    }

    /* Optional folder filter
     */
    if (is_numeric($folderId)) {
        try {
            $folderMapper = new \Schoolbox\Resource\Folder\Mapper();
            $folder = $folderMapper->findById($folderId);
        } catch (\Exception $e) {
        }
    }

    /* Find the articles
    findForFolder() automatically filters out articles that have expired.
     */
    $newsMapper = NewsMapper::getInstance();
    $allNews = $newsMapper->findForFolder($folder->getId(), new Schoolbox\User());

    /* Render all this stuff
     */
    $page->setFile(TEMPLATE_body, 'feed/rss.xml');
    $page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

    $page->setVar(
        array(
            'TITLE' => Scrub::xml(Cfg::get('school_name') . ' - '.$folder->getTitle().' News'),
            'DESCRIPTION' => Scrub::xml('Recent news items from Schoolbox'),
            'DATE' => Scrub::xml(date('r')),
            'LINK' => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . '/homepage/' . $folder->getId() ),
            'SELF' => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
        )
    );

    if ($allNews->count()) {
        foreach ($allNews as $currentNews) {
            /* Image detection (category news has no images)
             */
            $imageStr = '';

            /* if (($currentNews instanceof Schoolbox\News\Article) && !is_null($currentNews->getImage())) {
                 $imageStr = "<img src=\"" . $page->conType(
                     ) . $_SERVER['HTTP_HOST'] . "/storage/image.php?hash={$currentNews->getImage()->getHash(
                     )}&size=constrain200\">";
             }
            */

            $page->setVar(
                array(
                    'ITEM_TITLE' => Scrub::xml($currentNews->getHeading()),
                    'ITEM_TOPIC' => Scrub::xml($folder->getTitle()),
                    'ITEM_DESCRIPTION' => "<![CDATA[" . Scrub::html($imageStr . $currentNews->getBody()) . "]]>",
                    'ITEM_DATE' => Scrub::xml($currentNews->getPublishDate()->getUsingFormat(datetime::RFC2822)),
                    'ITEM_LINK' => Scrub::xml(
                            $page->conType() . $_SERVER['HTTP_HOST'] . '/homepage/news/' . $currentNews->getCategory()->getId() . '/' . $currentNews->getId()
                        ),
                )
            );

                $page->parse('rss_item_block', 'rss_item', true);
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

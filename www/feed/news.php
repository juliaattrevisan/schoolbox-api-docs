<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

$page = new SitePage(false);
$newsHandler = new News;
$page->setFile(TEMPLATE_body, 'feed/rss.xml');
$page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

$output = array(
    'TITLE'       => Scrub::xml(Cfg::get('school_name') . ' - News'),
    'DESCRIPTION' => Scrub::xml('Recent news items from the portal'),
    'DATE'        => Scrub::xml(date('r')),
    'LINK'        => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . '/news/'),
    'SELF'        => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']),
);
$page->setVar($output);

$groupHandler = new Group();

$newsList = $newsHandler->getAllByGroup($groupHandler->getStudentGroupTypeIds(), array(1,2,3));

if (is_array($newsList)) {
    foreach ($newsList as $news) {
        $itemDate = new Alaress_Date_Format($news['item_datetime_posted'], 'U');
        $image = $news['item_image']
            ? "<img src=\"" . $page->conType() . $_SERVER['HTTP_HOST'] ."/storage/image.php?hash={$news['item_image']}&size=constrain200\">"
            : '';
        $output = array(
            'ITEM_TITLE'       => Scrub::xml($news['item_heading']),
            'ITEM_DESCRIPTION' => "<![CDATA[" . Scrub::html($image . $news['item_body']) . "]]>",
            'ITEM_DATE'        => Scrub::xml($itemDate->toRssString()),
            'ITEM_LINK'        => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . '/news/item/' . $news['item_id']),
        );
        $page->setVar($output);
        $page->parse('rss_item_block', 'rss_item', true);
    }
}
else {
    $page->setVar('rss_item_block', '');
}

header('Content-type: application/rss+xml; charset=UTF-8');
$page->outputPage(true);
exit;

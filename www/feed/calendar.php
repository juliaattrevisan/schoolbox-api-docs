<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage(false);
$calendarHandler = new Calendar;

$page->setFile(TEMPLATE_body, 'feed/rss.xml');
$page->setBlock(TEMPLATE_body, 'rss_item', 'rss_item_block');

$output = array(
    'TITLE'       => Scrub::xml(Cfg::get('school_name') . ' - Calendar'),
    'DESCRIPTION' => Scrub::xml('Today\'s events'),
    'DATE'        => Scrub::xml(date('r')),
    'LINK'        => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . '/calendar/week/'),
    'SELF'        => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']),
);
$page->setVar($output);

$groupHandler = new Group();

$calendarList = $calendarHandler->getDayByGroup(time(), $groupHandler->getStudentGroupTypeIds(), array(1,2,3));

if (is_array($calendarList)) {
    foreach ($calendarList as $calendar) {

        $time = '';

        if ($calendar['startTime'] == '0:00' || strtolower($calendar['startTime']) == '12:00am' || strtolower($calendar['startTime']) == '12:00 am' || $calendar['startTime'] == '') {
            $time = 'All day';
        }
        else if ($calendar['endTime'] == '0:00' || strtolower($calendar['endTime']) == '12:00am' || strtolower($calendar['endTime']) == '12:00 am' || $calendar['endTime'] == '') {
            $time = $calendar['startTime'];
        }
        else {
            $time = $calendar['startTime'].' - '.$calendar['endTime'];
        }

        $itemDate = new Alaress_Date_Format($calendar['date'], 'Y-m-d');

        $output = array(
            'ITEM_TITLE'       => Scrub::xml($calendar['note']),
            'ITEM_DESCRIPTION' => Scrub::xml($time),
            'ITEM_DATE'        => Scrub::xml($itemDate->getShort()),
            'ITEM_LINK'        => Scrub::xml($page->conType() . $_SERVER['HTTP_HOST'] . '/calendar/week/'),
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

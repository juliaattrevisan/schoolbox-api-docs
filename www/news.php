<?php
/**
 * News Archive
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$this->setFile(TEMPLATE_content, 'news.html');
$this->setBlock(TEMPLATE_content, 'add_news', 'add_news_block');
$this->setBlock(TEMPLATE_content, 'topic_header', 'topic_header_block');
$this->setBlock(TEMPLATE_content, 'topic', 'topic_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'archive_button', 'archive_button_block');
$this->setBlock(TEMPLATE_content, 'year_filter', 'year_filter_block');
$this->setBlock(TEMPLATE_content, 'month_filter', 'month_filter_block');
$this->setBlock(TEMPLATE_content, 'year', 'year_block');
$this->setBlock('year', 'month', 'month_block');
$this->setBlock('topic', 'news', 'news_block');
$this->setTab('news');

$groupHandler = new Group(NEWS_SERVICE);
$newsHandler = new News();

$archive = false;

if($this->arg[0] == 'archive') {
    $archive = true;
    $filter = array(
        'topic' => $this->arg[1],
        'year'  => $this->arg[2],
        'month' => $this->arg[3],
        'page'  => $this->arg[4]
    );
} else {
    $filter = array(
        'topic' => $this->arg[0],
        'year'  => $this->arg[1],
        'month' => $this->arg[2],
        'page'  => $this->arg[3]
    );
}

// set an appropriate title
$title = $archive ? 'News Archive' : 'News';
$this->setTitle($title);
$this->setVar('PAGE_TITLE', $title);

// get list of possible topics
$validTopics = array();
foreach($newsHandler->getTopicList() as $elem => $topic) {
    $validTopics[] = strtolower($topic['name']);
}

// sanitise filter inputs
if(!in_array($filter['topic'], $validTopics))
    $filter['topic'] = 'all';

// if year/month/page are null or something stupid, set to default
$defaults = array(
    'year'  => 'all',
    'month' => 'all',
    'page'  => 1
);

foreach ($defaults as $field => $default)
    if(!is_numeric($filter[$field]))
        $filter[$field] = $default;

// add archive to the filter post-sanitise
$filter['archive'] = $archive ? 'archive/' : '';

$this->setVar('topic_header_block', '');

// display add news button if user is allowed to
if (($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS) {
    $this->parse('add_news_block', 'add_news');
} else {
    $this->setVar('add_news_block', '');
}

// does the bottom button link to archived or current news?
$this->setVar(array(
    'ARCH_CURRENT' => $archive ? 'Current' : 'Archived',
    'ARCH_CURRENT_CLEAN' => $archive ? '' : 'archive/',
));
$this->parse('archive_button_block', 'archive_button');

/* Year and Month List */
$dateList = $newsHandler->getOccupiedDateList($archive, $filter['topic']);

if ( $dateList ) {
    foreach ($dateList as $i => $date) {

        $this->setVar( array(
            'YEAR' => $date['year'],
            'YEAR_ClASS' => $date['year'] == $filter['year'] ? 'selected' : ''
        ) );

        $month = new Alaress_Date_Format($date['item_datetime_published'], 'y-m-d');

        /* Highlight only for the selected month and selected year
           */
        if (($month->getUsingFormat('m') == $filter['month']) && ($month->getUsingFormat('Y') == $filter['year'])) {
            $monthClass = 'selected';
        }
        else {
            $monthClass = '';
        }

        $this->setVar( array(
            'MONTH'            => $month->getUsingFormat('F'),
            'MONTH_NUMBER'     => $month->getUsingFormat('m'),
            'MONTH_CLASS'      => $monthClass,
            'MENU_TOPIC_CLEAN' => $filter['archive'] . $filter['topic']
        ) );

        $this->parse('month_block', 'month', true);

        $nextYear = $dateList[$i+1]['year'];

        if ( $date['year'] != $nextYear || empty($nextYear) ) {
            $this->parse('year_block', 'year', true);
            $this->setVar('month_block', '');
        }
    }
}

// setup the news articles to display
// $newsList = $newsHandler->getNews(0, true);
$newsList = $newsHandler->getNewsListing($archive, $filter['topic'], $filter['year'], $filter['month']);

// setup pagination for archive
$newsPerPage = Cfg::get('search_type_limit');
$totalPages = ceil(count($newsList) / $newsPerPage);
$newsIndex = $newsPerPage * ($filter['page'] - 1);

// figure out which pagination buttons should be displayed
if($filter['page'] < 2)
    $this->setVar('previous_button_block', '');
else
    $this->parse('previous_button_block', 'previous_button');

if($filter['page'] >= $totalPages)
    $this->setVar('next_button_block', '');
else
    $this->parse('next_button_block', 'next_button');

// set pagination button destinations
$pageSlug = "${filter['archive']}${filter['topic']}/${filter['year']}/${filter['month']}/";

if($totalPages > 1) {
    for($j = 1; $j <= $totalPages; $j++) {
        if($filter['page'] == $j)
            $this->setVar('CURRENT', ' current');
        else
            $this->setVar('CURRENT', '');

        $this->setVar('PAGE_INDEX', $j);
        $this->setVar('PAGE_INDEX_REF', $pageSlug . $j);

        $this->parse('page_number_block','page_number', true);
    }
}
else
    $this->setVar('page_number_block', '');

$outputPagination = array(
    'PREVIOUS_PAGE' => $pageSlug . ($filter['page'] - 1),
    'NEXT_PAGE'		=> $pageSlug . ($filter['page'] + 1),
    'LAST_PAGE'		=> $pageSlug . $totalPages,
);

$this->setVar($outputPagination);

$items = $newsHandler->getNewsListing($archive, $filter['topic'], $filter['year'], $filter['month'], $newsIndex, $newsPerPage);

if (is_array($items)) {
    $i = 0;
    $total = count($items);
    $userHandler = new User();

    $topicList['All'] = 1;

    if($items) {
        foreach ($items as $article)
        {
            $i++;

            if (($permission >= WRITE_ACCESS && $article['item_owner'] == $_SESSION['id']) || $permission >= FULL_ACCESS) {
                $this->setVar("MODIFY", " | <a href=\"/news/modify/" . $article['item_id'] . "\">modify</a>");
            }
            else
            {
                $this->setVar("MODIFY", "");
            }

            $fullname = $userHandler->formatName($article);
            $owner = "<a href=\"/search/user/{$article['uid']}\">$fullname</a>";

            $url = new Url($previous['topicName']);
            $expiry = $archive ? ', expired on ' : ', expires on ';

            $formatter = new TextFormatter();

            $output = array(
                'NEWS_ID' => $article['item_id'],
                'NEWS_TITLE' => $article['item_heading'],
                'POSTER' => $owner,
                'DATETIME' => $_SESSION['su'] == 1 ? $article['post_datetime'] . $expiry . $article['expire_datetime'] : $article['post_datetime'],
                'NEWS_BODY' => $formatter->cutSentence(Scrub::html($article['item_body']), 200),
                'TOPIC_NAME' => Scrub::text($previous['topicName']),
                'TOPIC_CLEAN' => $filter['archive'] . $url->getSlug(),
            );

            $this->setVar($output);
            $topicList[$article['topicName']] = 1;

            $url = new Url($article['topicName']);

            if ($article['topicName'] != $previous['topicName'] && $i > 1) {

                /* End topic
                    */
                $this->parse('topic_block', 'topic', true);
                $this->setVar("news_block", "");
                $this->parse("news_block", "news", true);

                /* Last rotation
                    */
                if ($total == $i) {
                    $this->setVar('TOPIC_NAME', Scrub::text($article['topicName']));
                    $this->setVar('TOPIC_CLEAN', $filter['archive'] . strtolower($url->getSlug()));
                    $this->parse('topic_block', 'topic', true);
                }
            }
            else {
                if ($total == $i) {
                    /* Last rotation
                         */
                    $this->parse("news_block", "news", true);
                    $this->setVar('TOPIC_NAME', Scrub::text($article['topicName']));
                    $this->setVar('TOPIC_CLEAN', $filter['archive'] . strtolower($url->getSlug()));

                    $this->parse('topic_block', 'topic', true);
                }
                else {
                    /* normal rotation
                         */
                    $this->parse("news_block", "news", true);
                }
            }

            $previous = $article;
        }
    } else {
        $this->setVar('topic_block', 'No news items found.');
        $this->setVar('year_block', '');
    }

    // prepend all onto our topic list
    $validTopics = array_merge(array("all"), $validTopics);

    // output tabs
    if (is_array($validTopics)) {
        foreach ($validTopics as $i => $topic) {
            $url = new Url($topic);

            $output = array(
                'TOPIC_CLASS' => strtolower($topic) == $filter['topic'] ? 'class="selected"' : '',
                'NEWS_TOPIC' => Scrub::text(ucwords($topic)),
                'TOPIC_CLEAN' => $filter['archive'] . strtolower($url->getSlug())
            );

            $this->setVar($output);
            $this->parse('topic_header_block', 'topic_header', true);
        }
    }
    else {
        $this->setVar('topic_block', '');
    }
}
else
{
    $this->setVar("topic_block", "No news items found");
}


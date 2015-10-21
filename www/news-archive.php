<?php
/**
 * News Archive
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 * * * * * * * *
 * Pseudocode framework
 * * * * * * * *
1. Am I in archive or am I in current?
$timePeriod =? archive : current;

2. Get the list of news articles that I'm allowed to see within this $timePeriod.
Do not filter by topic yet.
$visibleList = [];

3. Have I selected a topic?
3a. Topic selected
Get the $visibleListTopicSubset of $visibleList for this topic.
Get the $occupiedDateList from $visibleListTopicSubset

3b. 'All' selected
Get the $occupiedDateList from $visibleList

4. Have I selected a month?
4a. Month selected
Filter $visibleList[TopicSubset] into $visibleList[TopicSubset]MonthSubset

4b. No month selected

5. Output everthing
5.1. Display the [archive|current] button
5.2. Display the topics of $visibleList
5.3. Display the $occupiedDateList from $visibleList[TopicSubset]
5.4. Display the articles in $visibleList[TopicSubset][MonthSubset]
 *
 */

$this->setFile(TEMPLATE_content, 'news.html');
$this->setBlock(TEMPLATE_content, 'crumb_year', 'crumb_year_block');
$this->setBlock(TEMPLATE_content, 'crumb_month', 'crumb_month_block');
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
$this->setBlock('news', 'has_modify_button', 'has_modify_button_block');
$this->setBlock('news', 'has_publish_date', 'has_publish_date_block');
$this->setBlock('news', 'has_expiry', 'has_expiry_block');
$this->setBlock('news', "news_image", "news_image_block");


$this->setVar([
    'crumb_year_block'   => '',
    'crumb_month_block'  => '',
    'topic_header_block' => ''
]);

$this->setTab('news');

$groupHandler = new Group(NEWS_SERVICE);
$newsHandler = new News();


// determine whether to show archive or current context, and setup filters accordingly
$archive = true;
$filter = array(
    'topicSlug' => $this->arg[0],
    'year' => $this->arg[1],
    'month' => $this->arg[2],
    'page' => $this->arg[3]
);

// set an appropriate title based on news context
$title = $archive ? 'News Archive' : 'News';
$this->setTitle($title);
$this->setVar('PAGE_TITLE', $title);

// collate the full list of news the current user is allowed to see in the current context
// this is used to populate the topic headers
$visibleList = $newsHandler->getNewsListing($archive);

// get list of visibly populated topics
$topicSlugList = array('all' => 'all');

if (is_array($visibleList) && count($visibleList)) {
    foreach ($visibleList as $article) {
        $url = new Url($article['topicName']);
        $topicSlugList[$url->getSlug()] = $article['topicName'];
    }
}

// sanitise filter inputs
if (!array_key_exists($filter['topicSlug'], $topicSlugList)) {
    $filter['topicSlug'] = 'all';
}
$filter['topic'] = $topicSlugList[$filter['topicSlug']];

// if year/month/page are null or something stupid, set to default
$defaults = array(
    'year' => 'all',
    'month' => 'all',
    'page' => 1
);

if (is_numeric($filter['year'])) {
    $this->setVar('SELECTED_YEAR', $filter['year']);
    $this->parse('crumb_year_block', 'crumb_year');
}

if (is_numeric($filter['month'])) {
    $this->setVar('SELECTED_MONTH_NAME', DateTime::createFromFormat('!m', $filter['month'])->format('F'));
    $this->setVar('SELECTED_MONTH', $filter['month']);
    $this->parse('crumb_month_block', 'crumb_month');
}


foreach ($defaults as $field => $default) {
    if (!is_numeric($filter[$field])) {
        $filter[$field] = $default;
    }
}


// collate the full list of occupied dates for this category
// this is used to populate the archive sidebar
$occupiedDateList = $newsHandler->getOccupiedDateList($archive, $filter['topic']);

// finally get the list of news that is visible, based on the filters applied
$visibleListSubset = $newsHandler->getNewsListing($archive, $filter['topic'], $filter['year'], $filter['month']);


// display add news button if user is allowed to
if (($permission = $groupHandler->getPermission(0)) >= WRITE_ACCESS) {
    $this->parse('add_news_block', 'add_news');
} else {
    $this->setVar('add_news_block', '');
}


/* Year and Month List */
if ($occupiedDateList) {
    foreach ($occupiedDateList as $i => $date) {

        $this->setVar(
            array(
                'YEAR' => $date['year'],
                'YEAR_ClASS' => $date['year'] == $filter['year'] ? 'current' : ''
            )
        );

        /* Highlight only for the selected month and selected year
           */
        if (($date['month'] == $filter['month']) && ($date['year'] == $filter['year'])) {
            $monthClass = 'current';
        } else {
            $monthClass = '';
        }

        $format = new Alaress_Date_Format('70-' . $date['month'] . '-01', 'Y-m-d');

        $this->setVar(
            array(
                'MONTH' => $format->getUsingFormat('F'),
                'MONTH_NUMBER' => $date['month'],
                'MONTH_CLASS' => $monthClass,
                'MENU_TOPIC_CLEAN' => $filter['archive'] . $filter['topicSlug'],
                'MENU_TOPIC_NAME'  => ucwords($filter['topic'])
            )
        );

        $this->parse('month_block', 'month', true);

        $nextYear = $occupiedDateList[$i + 1]['year'];

        if ($date['year'] != $nextYear || empty($nextYear)) {
            $this->parse('year_block', 'year', true);
            $this->setVar('month_block', '');
        }
    }
} else {
    $this->setVar('year_block', '');
}


// setup pagination for display
$newsPerPage = Cfg::get('search_type_limit');
$totalPages = ceil(count($visibleListSubset) / $newsPerPage);
$newsIndex = $newsPerPage * ($filter['page'] - 1);

// figure out which pagination buttons should be displayed
if ($filter['page'] < 2) {
    $this->setVar('previous_button_block', '');
} else {
    $this->parse('previous_button_block', 'previous_button');
}

if ($filter['page'] >= $totalPages) {
    $this->setVar('next_button_block', '');
} else {
    $this->parse('next_button_block', 'next_button');
}

// set pagination button destinations
$pageSlug = "${filter['archive']}${filter['topicSlug']}/${filter['year']}/${filter['month']}/";

if ($totalPages > 1) {
    for ($j = 1; $j <= $totalPages; $j++) {
        if ($filter['page'] == $j) {
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
    'PREVIOUS_PAGE' => $pageSlug . ($filter['page'] - 1),
    'NEXT_PAGE' => $pageSlug . ($filter['page'] + 1),
    'LAST_PAGE' => $pageSlug . $totalPages,
);

$this->setVar($outputPagination);

// does the bottom button link to archived news?
if ($archive) {
    $this->setVar('archive_button_block');
} else {
    $this->setVar(
        array(
            'ARCH_CURRENT' => 'Archived',
            'ARCH_CURRENT_CLEAN' => "archive/${filter['topicSlug']}/",
        )
    );
    $this->parse('archive_button_block', 'archive_button');
}

$items = $newsHandler->getNewsListing(
    $archive, $filter['topic'], $filter['year'], $filter['month'], $newsIndex, $newsPerPage
);

if (is_array($items)) {
    $i = 0;
    $total = count($items);
    $userHandler = new User();

    $topicList['All'] = 1;

    if ($items) {
        $j = 0;

        foreach ($items as $article) {
            $i++;

            /* Find out if admin-specific kind of information should be displayed with the
             * news article
             */
            $hasAdminAccess = ($permission >= WRITE_ACCESS && $article['item_owner'] == $_SESSION['id']) || $permission >= FULL_ACCESS;

            /* Offer a Modify button if you are allowed to alter this new item's contents
             */
            if ($hasAdminAccess) {
                $this->setVar(
                    array(
                        'HAS_MODIFY_BUTTON_ITEM_ID' => $article['item_id'],
                    )
                );

                $this->parse('has_modify_button_block', 'has_modify_button');
            } else {
                $this->setVar('has_modify_button_block', '');
            }

            /* Show publish date to administrators if it's in the future
             */
            if ($hasAdminAccess) {
                $publishDate = new Alaress_Date_Format($article['item_datetime_published'], Alaress_Date_Format::DATABASE_FORMAT);

                if ($publishDate->getUnixTimestamp() > time()) {
                    $this->setVar(
                        array(
                            'PUBLISH_DATE' => $publishDate->getDateOnly(),
                        )
                    );

                    $this->parse('has_publish_date_block', 'has_publish_date');
                } else {
                    $this->setVar('has_publish_date_block', '');
                }
            } else {
                $this->setVar('has_publish_date_block', '');
            }

            /* Expiry settings
             */
            if ($hasAdminAccess) {
                if ($archive) {
                    $expiryVerb = 'Expired';
                } else {
                    $expiryVerb = 'Expires';
                }

                $expireDate = new Alaress_Date_Format($article['expire_datetime'], 'U');
                
                $this->setVar(
                    array(
                        'EXPIRY_VERB' => $expiryVerb,
                        'EXPIRY_DATE' => $expireDate->getLongDateOnly(),
                    )
                );

                $this->parse('has_expiry_block', 'has_expiry');
            } else {
                $this->setVar('has_expiry_block', '');
            }

            if ($article['item_image']) {
                $this->setVar('NEWS_IMAGE', $article['item_image']);
                $this->parse('news_image_block', 'news_image');
            } else {
                $this->setVar('news_image_block','');
            }


            /* The usual stuff
             */
            $fullname = $userHandler->formatName($article);

            $url = new Url($previous['topicName']);

            $formatter = new TextFormatter();
            
            $postDate = new Alaress_Date_Format($article['post_datetime'], 'U');

            $output = array(
                'NEWS_ID' => $article['item_id'],
                'NEWS_TITLE' => $article['item_heading'],
                'POSTER' => $fullname,
                'POSTER_ID' => $article['uid'],
                'POSTER_EXTERNAL_ID' => $article['synid'],
                'DATETIME' => $postDate->getLong(),
                'NEWS_BODY' => TextFormatter::cutSentence(Scrub::html2text($article['item_body']), 300),
                'TOPIC_NAME' => Scrub::text($previous['topicName']),
                'TOPIC_CLEAN' => $filter['archive'] . $url->getSlug(),
                'ON_OFF' => ++$j % 2 ? 'dark' : ''
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
            } else {
                if ($total == $i) {
                    /* Last rotation
                         */
                    $this->parse("news_block", "news", true);
                    $this->setVar('TOPIC_NAME', Scrub::text($article['topicName']));
                    $this->setVar('TOPIC_CLEAN', $filter['archive'] . strtolower($url->getSlug()));

                    $this->parse('topic_block', 'topic', true);
                } else {
                    /* normal rotation
                         */
                    $this->parse("news_block", "news", true);
                }
            }

            $previous = $article;
        }
    } else {
        $this->setVar('topic_block', '');
        $this->setVar('year_block', 'No news items found.');
    }

    // output tabs
    if (is_array($topicSlugList)) {
        foreach ($topicSlugList as $slug => $topic) {
            $output = array(
                'TOPIC_CLASS' => strtolower($topic) == strtolower($filter['topic']) ? 'class="current"' : '',
                'NEWS_TOPIC' => Scrub::text(ucwords($topic)),
                'TOPIC_CLEAN' => $filter['archive'] . $slug
            );

            $this->setVar($output);
            $this->parse('topic_header_block', 'topic_header', true);
        }
    } else {
        $this->setVar('topic_block', '');
    }
} else {
    $this->setVar('topic_block', '');
    $this->setVar('year_block', 'No news items found.');
}


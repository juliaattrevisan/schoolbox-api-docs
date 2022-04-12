<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, '/homepage/chatArchive.html');
$this->setBlock(TEMPLATE_content, 'add_chat', 'add_chat_block');
$this->setBlock(TEMPLATE_content, 'category_header', 'category_header_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'year_filter', 'year_filter_block');
$this->setBlock(TEMPLATE_content, 'month_filter', 'month_filter_block');
$this->setBlock(TEMPLATE_content, 'year', 'year_block');
$this->setBlock('year', 'month', 'month_block');
$this->setBlock(TEMPLATE_content, 'chat_date', 'chat_date_block');
$this->setBlock('chat_date', 'chat', 'chat_block');
$this->setVar('category_header_block', '');


$groupHandler = new Group();
$folderMapper = new \Schoolbox\Resource\Folder\Mapper();
$chatHandler = new Chat();

if (!is_numeric($this->arg[0] ?? null)) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
}

// setup filters
$filter = array(
    'folder' => $this->arg[0],
    'year' => $this->arg[1] ?? date('Y'),
    'month' => $this->arg[2] ?? date('m'),
    'page' => $this->arg[3] ?? 1
);

// if the category doesn't exist, throw an error
try {
    $folder = $folderMapper->findById($filter['folder']);

    // Check if user has a permission to read this page
    if (Access::getInstance()->getFolderAccess($_SESSION['user'], $folder->getId()) < UserAccess::ACCESS_READ) {
        error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
    }
} catch (\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
}

$this->setTitle("Chat Archive: {$folder->getName()}");
$this->setVar('CATEGORY_TITLE', $folder->getName());
$this->setVar('CATEGORY_ID', $folder->getId());

/* Render out breadcrumbs
 */
$trail = new \Schoolbox\Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Chat Archive',
        'href' => '',
        'class' => 'active',
    ]
]);

// collate the full list of chat the current user is allowed to see in the current context
// this is used to populate the category headers
$visibleList = $chatHandler->get_logs($folder->getId());


// sanitise filter inputs
// if year/month/page are null or something stupid, set to default
$defaults = array(
    'year' => 'all',
    'month' => 'all',
    'page' => 1
);
foreach ($defaults as $field => $default) {
    if (!is_numeric($filter[$field])) {
        $filter[$field] = $default;
    }
}

// collate the full list of occupied dates for this category
// this is used to populate the sidebar
$occupiedDateList = $chatHandler->getOccupiedDateList($filter['folder']);

// finally get the list of chat that is visible, based on the filters applied
$visibleListSubset = $chatHandler->getChatListing($filter['folder'], $filter['year'], $filter['month']);

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
                'MENU_CATEGORY_CLEAN' => $filter['folder']
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
$chatPerPage = Config::get('search_type_limit');
$totalPages = is_array($visibleListSubset) ? ceil(count($visibleListSubset) / $chatPerPage) : 0;
$chatIndex = $chatPerPage * ($filter['page'] - 1);

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
$pageSlug = "${filter['folder']}/${filter['year']}/${filter['month']}/";

if ($totalPages > 1) {
    for ($j = 1; $j <= $totalPages; $j++) {
        if ($filter['page'] == $j) {
            $this->setVar('CURRENT', ' current');
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

$items = $chatHandler->getChatListing($filter['folder'], $filter['year'], $filter['month'], $chatIndex, $chatPerPage);

if (is_array($items)) {
    $i = 0;
    $total = count($items);
    $userHandler = new User();
    $userList = array();
    $categoryList = array();

    if ($items) {
        foreach ($items as $chat) {
            $i++;

            // get the datestamp for the post
            if (isset($previous)) {
                $df = new Alaress_Date_Format($previous['time'], Alaress_Date_Format::DATABASE_FORMAT);
            } else {
                $df = new Alaress_Date_Format($chat['time'], Alaress_Date_Format::DATABASE_FORMAT);
            }

            $postDay = $df->getLongDateOnly();

            // don't query the db for users more than we have to
            if (isset($userList[$chat['user_id']])) {
                $speaker = $userList[$chat['user_id']];
            } else {
                $speaker = $userHandler->getByID($chat['user_id']);
                $userList[$chat['user_id']] = $speaker;
            }

            $fullname = $userHandler->formatName($speaker);
            $owner = "<a href=\"/search/user/{$chat['user_id']}\">$fullname</a>";

            $formatter = new TextFormatter();

            $output = array(
                'CHAT_ID' => $chat['id'],
                'CHAT_TITLE' => $owner,
                'POSTER' => $owner,
                'BLOG_AUTHOR_ID' => $chat['user_id'],
                'DATETIME' => $chat['time'],
                'CHAT_BODY' => Scrub::text($chat['body']),
                'CHAT_DATE' => $postDay,
            );

            $this->setVar($output);
            $categoryList[$chat['time']] = 1;

            if ($chat['date'] != $previous['date'] && $i > 1) {
                $this->parse('chat_date_block', 'chat_date', true);
                $this->setVar("chat_block", "");
                $this->parse("chat_block", "chat", true);

                if ($total == $i) {
                    $lastDate = new Alaress_Date_Format($chat['time'], Alaress_Date_Format::DATABASE_FORMAT);
                    $this->setVar('CHAT_DATE', $lastDate->getLongDateOnly());
                    $this->parse('chat_date_block', 'chat_date', true);
                }
            } else {
                if ($total == $i) {
                    /* Last rotation
                     */
                    $this->parse("chat_block", "chat", true);
                    $this->parse('chat_date_block', 'chat_date', true);
                } else {
                    /* normal rotation
                     */
                    $this->parse("chat_block", "chat", true);
                }
            }

            $previous = $chat;
        }
    } else {
        $this->setVar('chat_date_block', '');
        $this->setVar('year_block', 'No chat items found.');
    }
} else {
    $this->setVar('chat_date_block', '');
    $this->setVar('year_block', 'No chat items found.');
}

<?php

/* Global search result page
 */

/* Execution time
 */
$time_start = microtime(true);

require_once '../include/SitePage.php';
require_once '../library/group.php';
require_once '../library/category.php';
require_once '../library/allowed-file.php';
//require_once 'include/permission.php';

$page = new SitePage();
$group = new Group();
$categoryStd = new Category();
$allowedFile = new AllowedFile();

$keyword = preg_replace('/[^\ A-Za-z0-9\"\'\+\-_=\@\?!\.]/', '', $_GET['keyword']);

if (is_numeric($keyword) || ($keyword && strlen($keyword) > 2)) {
    // strip out any injection attempts
    $type = $_GET['type'];
    $current_page = empty($_GET['page']) ? 1 : $_GET['page'];

    $page->setVar(array(
        'UNESCAPED_KEYWORD' => $keyword,
    ));
} else {
    $page->setFile(TEMPLATE_content, 'search.html');
    $page->setBlock(TEMPLATE_content, 'type', 'type_block');
    $page->setBlock(TEMPLATE_content, 'result', 'result_block');
    $page->setBlock(TEMPLATE_content, 'no_result', 'no_result_block');
    // escape any quotes in the keyword string, or the javascript will explode.

    // setup default values
    $output = array(
        'KEYWORD' => str_replace('"', '\"', $keyword),
        'ENCODED_KEYWORD' => htmlentities($keyword),
        'UNESCAPED_KEYWORD' => $keyword,
        'FOR' => ' for ',
        'NO_RESULTS_BECAUSE' => '',
        'type_block' => '',
        'result_block' => '',
        'no_result_block' => ''
    );
    $page->setVar($output);


    // test the search as to why it failed, and return meaningful response to the user
    if (strlen($keyword) == 0) {
        $page->setVar('FOR', '');
        $page->setVar('NO_RESULTS_BECAUSE', ' because it\'s empty');
    } elseif (strlen($keyword) < 3)
        $page->setVar('NO_RESULTS_BECAUSE', ' because it needs to be longer, or wrapped in "quotes"');

    $page->parse('no_result_block', 'no_result');
    $page->outputPage();
    // finished rendering. return control to SitePage
    return true;
}

$handle = array(
    'user' => 'user',
    'file' => 'file',
    'category' => 'category',
    'link' => 'link',
    'news' => 'categoryNews',
    'sitenews' => 'news',
    'blog' => 'blog',
    'wiki' => 'wiki',
    'forum' => 'forum',
    'event' => 'calendar',
);

$type_list = array(
    'all' => 'All',
    'user' => 'People',
    'file' => 'Files',
    'category' => 'Folders',
    'link' => 'Links',
    'sitenews' => 'School News',
    'news' => 'Homepage News',
    'blog' => 'Blogs',
    'wiki' => 'Wikis',
    'forum' => 'Forums',
    'event' => 'Calendar',
);

$count = array();

// default case is to search for everthing
if (empty($type) || $type == 'all') {
    // perform search for each handle
    $page->setFile(TEMPLATE_content, 'search.html');
    $page->setBlock(TEMPLATE_content, 'type', 'type_block');
    $page->setBlock(TEMPLATE_content, 'result', 'result_block');
    $page->setBlock(TEMPLATE_content, 'no_result', 'no_result_block');
	$page->setBlock(TEMPLATE_content, 'see_more', 'see_more_block');
    // escape any quotes in the keyword string, or the javascript will explode.

    // setup default values
    $output = array(
        'KEYWORD' => str_replace('"', '\"', $keyword),
        'ENCODED_KEYWORD' => htmlentities($keyword),
        'UNESCAPED_KEYWORD' => $keyword,
        'FOR' => ' for ',
        'NO_RESULTS_BECAUSE' => '',
        'see_more_block' => '',
        'type_block' => '',
        'result_block' => '',
        'no_result_block' => ''
    );
    $page->setVar($output);

    // iterate through the handle types
    foreach ($handle as $type_name => &$type_handle) {
        // include & instantiate
        require_once("../library/$type_handle.php");
        $type_class = ucfirst($type_handle);
        $type_handle = new $type_class;

        $result[$type_name] = $type_handle->search($keyword, 0, Cfg::get('search_all_limit'), $count[$type_name]);
    }

    $count['all'] = array_sum($count);

    // are there any results?

    if ($count['all'] == 0) {
        $page->parse('no_result_block', 'no_result', false);
        // done. exit.
        $page->outputPage();
        return true;
    }

    // type list
    foreach ($type_list as $type_name => $type_desc) {
        if (!$count[$type_name])
            continue;
        $page->setVar(array(
            'TYPE_COUNT' => $count[$type_name],
            'TYPE_TITLE' => $type_name,
            'TYPE_CLASS' => $count[$type_name] == 0 ? 'disabled' : '',
            'TYPE_NAME' => $type_desc
        ));

        $page->setVar(array(
            'RESULT_TITLE' => $type_name,
            'RESULT_CLASS' => $type_name == 'all' ? 'hide loaded' : 'hide',
        ));

        if ($type_name != 'all') {
            $page->setVar('RESULT');
        }

        $page->parse('type_block', 'type', true);
        $page->parse('result_block', 'result', true);
    }
} //
elseif (!empty($handle[$type])) {
    // include & instantiate
    require_once("../library/{$handle[$type]}.php");
    $type_class = ucfirst($handle[$type]);
    $handle[$type] = new $type_class;

    /* Particular ordering and sorting for Categories
      */
    if (in_array($type_class, array('Category'))) {
        $result[$type] = $handle[$type]->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type], 'id', 'desc');
    } else {
        // perform search for individual category (without formatting for ajax call)
        $result[$type] = $handle[$type]->search($keyword, ($current_page - 1) * Cfg::get('search_type_limit'), Cfg::get('search_type_limit'), $count[$type]);
    }
}

/* <RESULTS RENDERING> */

// render users
if (!empty($result['user'])) {
    $page->setFile('user_t', 'search/user.html');
    $page->setBlock('user_t', 'user', 'user_block');
    $page->setBlock('user', 'due_work', 'due_work_block');
    $page->setBlock('user', 'timetable', 'timetable_block');
    $page->setBlock('user', 'helpdesk', 'helpdesk_block');
    $page->setBlock('user', 'eportfolio', 'eportfolio_block');
    $page->setBlock('user', 'modify', 'modify_block');
    $page->setBlock('user', 'boarder_leave', 'boarder_leave_block');

    $page->setVar(array(
        'USER_DISP_COUNT' => count($result['user']),
        'USER_COUNT' => $count['user'],
    ));

    foreach ($result['user'] as $user) {
        // Format email address
        $emailObj = new Email();
        $address = $emailObj->formatEmailFromArray($user);

        if ($address) {
            if (Cfg::get('site_location') == 'wenona') {
                $email = "<a href=\"{$_SESSION['mailhandle']}$address\">Send Email</a>";
            } else {
                $email = "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>";
            }
        } else {
            $email = '';
        }

        /* If a parent is performing the search, remove the email address
           */
        $groupHandler = new Group();
        $userHandler = new User();

        if ($groupHandler->isParent()) {
            unset($email);
        }
        
        /* If restrict_parent_emails and not a staff member, remove the email address */
        if ((Cfg::get('restrict_parent_emails') == '1') && $groupHandler->isParent($user['id']) && !$groupHandler->isStaff()) {
            unset($email);
        }
        
        $page->setVar(array(
            'USER_ID' => $user['id'],
            'USER_SYNERGY_ID' => $user['synergy_id'],
            'USER_NAME' => $userHandler->formatName($user),
            'USER_EMAIL' => $email,
            'USER_COUNT' => $count['user'],
            'USER_YEAR' => empty($user['year']) ? '' : "Year {$user['year']}",
            'USER_CAMPUS' => stristr($user['group_name'], $user['campus_name']) ? '' : $user['campus_name'],
            'USER_GROUP' => $user['group_name'],
        ));

        $page->setVar('helpdesk_block');
        $page->setVar('timetable_block');
        $page->setVar('eportfolio_block');
        $page->setVar('boarder_leave_block');

        if (!$group->isStudent($user['id']) || (!$group->isStaff() && !$group->isParentOfChild($user['id'])))
            $page->setVar('due_work_block');
        else
            $page->parse('due_work_block', 'due_work');

        //Show student timetables to staff and parents of child
        if ($group->isStudent($user['id'])) {
            if ($group->isStaff() || $group->isParentOfChild($user['id'])) {

                /* Timetable shouldn't appear if synergy is disabled in the conf
                 */
                if (Cfg::get('external_enable') && $user['synergy_id']) {
                    $page->parse('timetable_block', 'timetable');
                } else {
                    $page->setVar('timetable_block', '');
                }

                if (HELPDESK_enable)
                    $page->parse('helpdesk_block', 'helpdesk');
            }
            if (Cfg::get('boarder_leave_enabled') == '1') {
                if ($group->isStaff()) {
                    $page->parse('boarder_leave_block', 'boarder_leave');
                }
            }
        } else {
            $page->setVar('helpdesk_block');
            //Show staff timetables to other staff
            if ($group->isStaff() && $group->isStaff($user['id']))
                $page->parse('timetable_block', 'timetable');
        }

        if (!$_SESSION['su'])
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

		/* ePortfolio link
		 */
		$authenticator = new \Eportfolio_Authenticator($_SESSION['id'], $user['id'], 'profile');

		if ($authenticator->canView()) {
			$page->parse('eportfolio_block', 'eportfolio');
		}

		$page->parse('user_block', 'user', true);
    }

	outputSeeMore($page, $type, $count, 'user');

    $page->parse('RESULT', 'user_t', true);
}

// render files
if (!empty($result['file'])) {
    $page->setFile('file_t', 'search/file.html');
    $page->setBlock('file_t', 'file', 'file_block');
    $page->setBlock('file', 'modify', 'modify_block');
    $page->setVar(array(
        'FILE_DISP_COUNT' => count($result['file']),
        'FILE_COUNT' => $count['file'],
    ));

    $userHandler = new User();

    foreach ($result['file'] as $file) {
        foreach ($categoryStd->breadcrumb($file['category']) as $i => $breadcrumb) {
            $file['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $createdTime = new Alaress_Date_Relative($file['submitted']);

        $page->setVar(array(
            'FILE_ID' => $file['id'],
            'FILE_ICON' => $file['iconFile'],
            'FILE_SIZE' => Alaress_Byte_Format::bytesToString($file['file_size']),
            'FILE_DESCRIPTION' => Scrub::text($file['description']),
            'FILE_COMMENT' => TextFormatter::cutSentence(Scrub::text($file['comment']), 150),
            'FILE_OWNER' => $file['uid'],
            'FILE_OWNER_NAME' => $userHandler->formatName($file),
            'FILE_CREATED' => $createdTime->getRelativeTime(),
            'FILE_BREADCRUMB' => $file['breadcrumb'],
            'FILE_COUNT' => $count['file'],
        ));

        if ($file['perms_type'] < 2 && $file['userAccess_type'] < 2)
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        $page->parse('file_block', 'file', true);
    }

	outputSeeMore($page, $type, $count, 'file');

    $page->parse('RESULT', 'file_t', true);
}

// render categories
if (!empty($result['category'])) {
    $page->setFile('category_t', 'search/category.html');
    $page->setBlock('category_t', 'category', 'category_block');
    $page->setBlock('category', 'modify', 'modify_block');
    $page->setVar(array(
        'CATEGORY_DISP_COUNT' => count($result['category']),
        'CATEGORY_COUNT' => $count['category'],
    ));

    foreach ($result['category'] as $category) {
        foreach ($categoryStd->breadcrumb($category['id']) as $i => $breadcrumb)
            $category['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'CATEGORY_ID' => $category['id'],
            'CATEGORY_NAME' => $category['code'] ? Scrub::text($category['name']) . '(' . $category['code'] . ')' : Scrub::text($category['name']),
            'CATEGORY_CODE' => $category['code'],
            'CATEGORY_BREADCRUMB' => $category['breadcrumb'],
            'CATEGORY_COUNT' => $count['category'],
        ));

        if ($category['perms_type'] < 2 && $category['userAccess_type'] < 2)
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        $page->parse('category_block', 'category', true);
    }

	outputSeeMore($page, $type, $count, 'category');

    $page->parse('RESULT', 'category_t', true);
}

// render links
if (!empty($result['link'])) {
    $page->setFile('link_t', 'search/link.html');
    $page->setBlock('link_t', 'link', 'link_block');
    $page->setVar(array(
        'LINK_DISP_COUNT' => count($result['link']),
        'LINK_COUNT' => $count['link'],
    ));


    foreach ($result['link'] as $link) {
        foreach ($categoryStd->breadcrumb($link['category']) as $i => $breadcrumb) {
            $link['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $url = new Url($link['url']);

        if ($url->isLocalUrl()) {
            $linkUrl = $url->getRelativeURL();
            $linkTarget = '';
        } else {
            $linkUrl = $url->getBase64EncodedRedirect();
            $linkTarget = 'external';
        }

        $userHandler = new User();
        $createdTime = new Alaress_Date_Relative($link['submitted']);

        $page->setVar(array(
            'LINK_URL' => $linkUrl,
            'LINK_ICON' => $link['iconFile'],
            'LINK_TARGET' => $linkTarget,
            'LINK_DESCRIPTION' => Scrub::text($link['description']),
            'LINK_COMMENT' => TextFormatter::cutSentence(Scrub::text($link['comment']), 150),
            'LINK_OWNER' => $link['uid'],
            'LINK_OWNER_NAME' => $userHandler->formatName($link),
            'LINK_CREATED' => $createdTime->getRelativeTime(),
            'LINK_BREADCRUMB' => $link['breadcrumb'],
            'LINK_COUNT' => $count['link'],
        ));

        $page->parse('link_block', 'link', true);
    }

	outputSeeMore($page, $type, $count, 'link');

    $page->parse('RESULT', 'link_t', true);
}

// render homepage news
if (!empty($result['news'])) {
    $page->setFile('news_t', 'search/news.html');
    $page->setBlock('news_t', 'news', 'news_block');
    $page->setVar(array(
        'NEWS_DISP_COUNT' => count($result['news']),
        'NEWS_COUNT' => $count['news'],
    ));

    foreach ($result['news'] as $news) {
        foreach ($categoryStd->breadcrumb($news['code']) as $i => $breadcrumb) {
            $news['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        if (new DateTime() > new DateTime($news['datetime_expires'])) {
            $news['link'] = "/homepage/newsArchive/{$news['code']}#{$news['id']}";
        } else {
            $news['link'] = "/homepage/{$news['code']}#news-{$news['id']}";
        }

        $userHandler = new User();
        $createdTime = new Alaress_Date_Relative($news['datetime_posted']);

        $page->setVar(array(
            'NEWS_ID' => $news['id'],
            'NEWS_CODE' => $news['code'],
            'NEWS_LINK' => $news['link'],
            'NEWS_HEADING' => Scrub::text($news['heading']),
            'NEWS_BODY' => TextFormatter::cutSentence(Scrub::text(Scrub::html2text($news['body'])), 150),
            'NEWS_OWNER' => $news['uid'],
            'NEWS_OWNER_NAME' => $userHandler->formatName($news),
            'NEWS_CREATED' => $createdTime->getRelativeTime(),
            'NEWS_BREADCRUMB' => $news['breadcrumb'],
            'NEWS_COUNT' => $count['news'],
        ));

        $page->parse('news_block', 'news', true);
    }

	outputSeeMore($page, $type, $count, 'news');

    $page->parse('RESULT', 'news_t', true);
}


// render school news
if (!empty($result['sitenews'])) {
    $page->setFile('sitenews_t', 'search/schoolnews.html');
    $page->setBlock('sitenews_t', 'sitenews', 'sitenews_block');
    $page->setVar(array(
        'NEWS_DISP_COUNT' => count($result['news']),
        'NEWS_COUNT' => $count['news'],
    ));

    foreach ($result['sitenews'] as $news) {
        $tName = strtolower($news['topicName']);
        $archived = (new DateTime() > new DateTime($news['item_datetime_expires']));
        $news['breadcrumb'] = ($archived ? ' Archived' : 'Posted') . " in <a href=\"/news/" . ($archived ? 'archive/' : '') . "$tName\">{$news['topicName']}</a>";

        $news['link'] = "/news/item/{$news['item_id']}";

        $userHandler = new User();
        $createdTime = new Alaress_Date_Relative($news['item_datetime_posted']);

        $page->setVar(array(
            'NEWS_ID' => $news['item_id'],
            'NEWS_CODE' => $news['code'],
            'NEWS_LINK' => $news['link'],
            'NEWS_HEADING' => Scrub::text($news['item_heading']),
            'NEWS_BODY' => TextFormatter::cutSentence(Scrub::text(Scrub::html2text($news['item_body'])), 150),
            'NEWS_OWNER' => $news['item_owner'],
            'NEWS_OWNER_NAME' => $userHandler->formatName($news),
            'NEWS_CREATED' => $createdTime->getRelativeTime(),
            'NEWS_BREADCRUMB' => $news['breadcrumb'],
            'NEWS_COUNT' => $count['news'],
        ));

        $page->parse('sitenews_block', 'sitenews', true);
    }

	outputSeeMore($page, $type, $count, 'sitenews');

    $page->parse('RESULT', 'sitenews_t', true);
}


// render blog
if (!empty($result['blog'])) {
    $page->setFile('blog_t', 'search/blog.html');
    $page->setBlock('blog_t', 'blog', 'blog_block');
    $page->setVar(array(
        'BLOG_DISP_COUNT' => count($result['blog']),
        'BLOG_COUNT' => $count['blog'],
    ));

    $userHandler = new User();

    foreach ($result['blog'] as $blog) {
        foreach ($categoryStd->breadcrumb($blog['cid']) as $i => $breadcrumb) {
            $blog['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $createdTime = new Alaress_Date_Relative($blog['created']);

        $page->setVar(array(
            'BLOG_CID' => $blog['cid'],
            'BLOG_HEADING' => Scrub::text($blog['heading']),
            'BLOG_BODY' => TextFormatter::cutSentence(Scrub::text(Scrub::html2text($blog['body'])), 150),
            'BLOG_OWNER' => $blog['uid'],
            'BLOG_OWNER_NAME' => $userHandler->formatName($blog),
            'BLOG_CREATED' => $createdTime->getRelativeTime(),
            'BLOG_BREADCRUMB' => $blog['breadcrumb'],
            'BLOG_COUNT' => $count['blog'],
        ));

        $page->parse('blog_block', 'blog', true);
    }

	outputSeeMore($page, $type, $count, 'blog');

    $page->parse('RESULT', 'blog_t', true);
}

// render wiki
if (!empty($result['wiki'])) {
    $page->setFile('wiki_t', 'search/wiki.html');
    $page->setBlock('wiki_t', 'wiki', 'wiki_block');
    $page->setVar(array(
        'WIKI_DISP_COUNT' => count($result['wiki']),
        'WIKI_COUNT' => $count['wiki'],
    ));

    $userHandler = new User();

    foreach ($result['wiki'] as $wiki) {
        foreach ($categoryStd->breadcrumb($wiki['categoryID']) as $i => $breadcrumb) {
            $wiki['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $createdTime = new Alaress_Date_Relative($wiki['created']);

        $page->setVar(array(
            'WIKI_ID' => $wiki['wikiID'],
            'WIKI_CID' => $wiki['categoryID'],
            'WIKI_HEADING' => Scrub::text($wiki['heading']),
            'WIKI_BODY' => TextFormatter::cutSentence(Scrub::text(Scrub::html2text($wiki['body'])), 150),
            'WIKI_OWNER' => $wiki['userID'],
            'WIKI_OWNER_NAME' => $userHandler->formatName($wiki),
            'WIKI_CREATED' => $createdTime->getRelativeTime(),
            'WIKI_BREADCRUMB' => $wiki['breadcrumb'],
            'WIKI_COUNT' => $count['wiki'],
            'WIKI_SLUG' => $wiki['slug'],
        ));

        $page->parse('wiki_block', 'wiki', true);
    }

	outputSeeMore($page, $type, $count, 'wiki');

    $page->parse('RESULT', 'wiki_t', true);
}

// render forum
if (!empty($result['forum'])) {
    $page->setFile('forum_t', 'search/forum.html');
    $page->setBlock('forum_t', 'forum', 'forum_block');
    $page->setVar(array(
        'FORUM_DISP_COUNT' => count($result['forum']),
        'FORUM_COUNT' => $count['forum'],
    ));

    $userHandler = new User();

    foreach ($result['forum'] as $forum) {
        foreach ($categoryStd->breadcrumb($forum['categoryID']) as $i => $breadcrumb) {
            $forum['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
        }

        $createdTime = new Alaress_Date_Relative($forum['date']);

        $page->setVar(array(
            'FORUM_CID' => $forum['categoryID'],
            'FORUM_THREADID' => $forum['threadID'],
            'FORUM_TITLE' => Scrub::text($forum['threadTitle']),
            'FORUM_BODY' => TextFormatter::cutSentence(Scrub::text(Scrub::html2text($forum['body'])), 150),
            'FORUM_OWNER' => $forum['uid'],
            'FORUM_OWNER_NAME' => $userHandler->formatName($forum),
            'FORUM_CREATED' => $createdTime->getRelativeTime(),
            'FORUM_BREADCRUMB' => $forum['breadcrumb'],
        ));

        $page->parse('forum_block', 'forum', true);
    }

	outputSeeMore($page, $type, $count, 'forum');

    $page->parse('RESULT', 'forum_t', true);
}

// render event

if (!empty($result['event'])) {
    $page->setFile('event_t', 'search/event.html');
    $page->setBlock('event_t', 'event', 'event_block');
    $page->setVar(array(
        'EVENT_DISP_COUNT' => count($result['event']),
        'EVENT_COUNT' => $count['event'],
    ));

    foreach ($result['event'] as $event) {
        $date = new DateTime($event['date']);

        $event_time = '';

        if (!empty($event['startTime'])) {
            $event_time .= $event['startTime'];

            if (!empty($event['endTime'])) {
                $event_time .= ' to ' . $event['endTime'];
            }
        }

        $page->setVar(array(
            'EVENT_TITLE' => Scrub::text($event['note']),
            'EVENT_LINK' => '/calendar/week/' . $date->format('d-m-Y'),
            'EVENT_TYPE' => $event['eventType'],
            'EVENT_DETAIL' => Scrub::text($event['detail']),
            'EVENT_DATE' => $event['date'],
            'EVENT_LOCATION' => $event['location'],
            'EVENT_TIME' => $event_time
        ));

        $page->parse('event_block', 'event', true);
    }

	outputSeeMore($page, $type, $count, 'event');

    $page->parse('RESULT', 'event_t', true);
}

/* </RESULTS RENDERING> */


/* PAGINATION AND EXECUTION TIMES */

// if search type isn't specified
if (empty($type)) {
    // clear pagination
    $page->setBlock('RESULT', 'pagination', 'pagination_block');
    $page->setVar('pagination_block');

    // execution time
    $time = microtime(true) - $time_start;

    // render final output
    $page->outputPage();

// if we have a search type
} /* If we have a search type and have results to display
 */
else if ($result[$type] || $type == 'all') {

    // clear count
    $page->setBlock('RESULT', 'count', 'count_block');
    $page->setVar('count_block');

    // render pagination
    $page->setBlock('RESULT', 'pagination', 'pagination_block');

    $number_pages = ceil($count[$type] / Cfg::get('search_type_limit'));

    if ($number_pages > 1) {
        if (($current_page - 5) <= 1) {
            $start_window = 1;
            $end_window = $number_pages > 10 ? 10 : $number_pages;
        } elseif (($current_page + 5) >= $number_pages) {
            $start_window = $number_pages - 10;
            $end_window = $number_pages;
        } else {
            $start_window = $current_page - 5;
            $end_window = $current_page + 5;
        }

        $page->setVar(array(
            'PAGE_NAME' => '<',
            'PAGE_NUMBER' => '1',
            'PAGE_CLASS' => '',
        ));

        $page->parse('pagination_block', 'pagination', true);

        for ($i = $start_window; $i <= $end_window; $i++) {
            $page->setVar(array(
                'PAGE_NAME' => $i,
                'PAGE_NUMBER' => $i,
                'PAGE_CLASS' => $i == $current_page ? 'current' : '',
            ));

            $page->parse('pagination_block', 'pagination', true);
        }

        $page->setVar(array(
            'PAGE_NAME' => '>',
            'PAGE_NUMBER' => $number_pages,
            'PAGE_CLASS' => '',
        ));

        $page->parse('pagination_block', 'pagination', true);

        // only 1 page to display
    } else {
        $page->setVar('pagination_block');
    }

    // execution time
    $time = microtime(true) - $time_start;

    // render final output
    $page->parse(TEMPLATE_body, 'RESULT', true);
    $page->outputPage(true);
}


/**
 * @param SitePage $page
 * @param string $type
 * @param array $count
 * @param string $handle
 *
 * @return void
 */
function outputSeeMore($page, $type, $count, $handle) {

	$page->setBlock($handle . '_t', 'see_more', 'see_more_block');

    if($type == 'all' && $count[$handle] > Cfg::get('search_all_limit')) {
	    $page->setVar('MORE_COUNT', $count[$handle]);
	    $page->parse('see_more_block', 'see_more');
    }
	else {
		$page->setVar('see_more_block', '');
	}

}
?>

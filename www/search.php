<?php
/* 
 * Global search result page
 */

// execution time
$time_start = microtime(true);

require_once '../include/sitePage.php';
require_once '../library/group.php';
require_once '../library/category.php';
require_once '../library/allowed-file.php';

require_once 'include/permission.php';

$page        = new SitePage();
$group       = new Group();
$categoryStd = new Category();
$allowedFile = new AllowedFile();

if($_GET['keyword'] &&  strlen($_GET['keyword']) > 2) {
	$keyword      = $_GET['keyword'];
	$type         = $_GET['type'];
	$current_page = empty($_GET['page']) ? 1 : $_GET['page'];
}
else {
	error(45);
}

$handle = array(
    'user'     => 'user',
    'file'     => 'file',
    'category' => 'category',
    'link'     => 'link',
    'news'     => 'categoryNews',
    'blog'     => 'blog',
    'wiki'     => 'wiki',
    'forum'    => 'forum',
);

$type_list = array(
    'all'      => 'All',
    'user'     => 'People',
    'file'     => 'Files',
    'category' => 'Folders',
    'link'     => 'Links',
    'news'     => 'News',
    'blog'     => 'Blogs',
    'wiki'     => 'Wikis',
    'forum'    => 'Forums',
);

if (empty($type)) {
    // perform search for each handle
    $page->setFile(TEMPLATE_content, 'search.html');
    $page->setBlock(TEMPLATE_content, 'type', 'type_block');
    $page->setBlock(TEMPLATE_content, 'result', 'result_block');
    $page->setVar('KEYWORD', $keyword);

    foreach ($handle as $type_name => &$type_handle) {
        // include & instantiate
        require_once("../library/$type_handle.php");
        $type_class = ucfirst($type_handle);
        $type_handle = new $type_class;

        // search
        $result[$type_name] = $type_handle->search($keyword, 0, SEARCH_all_limit, $count[$type_name]);
    }

    $count['all'] = array_sum($count);

    // are there any results?
    if ($count['all'] == 0)
        error(45);

    // type list
    foreach ($type_list as $type_name => $type_desc) {
        if (!$count[$type_name])
            continue;

        $page->setVar(array(
            'TYPE_COUNT' => $count[$type_name],
            'TYPE_TITLE' => $type_name,
            'TYPE_CLASS' => $count[$type_name] == 0 ? 'disabled' : '',
            'TYPE_NAME'  => $type_desc
        ));

        $page->setVar(array(
            'RESULT_TITLE' => $type_name,
            'RESULT_CLASS' => $type_name == 'all' ? 'hide loaded' : 'hide',
        ));

        if($type_name != 'all')
            $page->setVar('RESULT');

        $page->parse('type_block', 'type', true);
        $page->parse('result_block', 'result', true);
    }
} elseif (!empty($handle[$type])) {
    // include & instantiate
    require_once("../library/{$handle[$type]}.php");
    $type_class = ucfirst($handle[$type]);
    $handle[$type] = new $type_class;

    // perform search for individual category (without formatting for ajax call)
    $result[$type] = $handle[$type]->search($keyword, ($current_page - 1) * SEARCH_type_limit, SEARCH_type_limit, $count[$type]);
}

// render users
if (!empty($result['user'])) {
    $page->setFile('user_t', 'search/user.html');
    $page->setBlock('user_t', 'user', 'user_block');
    $page->setBlock('user', 'due_work', 'due_work_block');
    $page->setBlock('user', 'timetable', 'timetable_block');
    $page->setBlock('user', 'helpdesk', 'helpdesk_block');
    $page->setBlock('user', 'eportfolio', 'eportfolio_block');
    $page->setBlock('user', 'modify', 'modify_block');
    $page->setVar(array(
        'USER_DISP_COUNT' => count($result['user']),
        'USER_COUNT'      => $count['user'],
    ));

    foreach ($result['user'] as $user) {
        // format email address
        $address = formatEmail($user);
        if ($address) {
            if (SITE_location == 'wenona')
                $email = "<a href=\"{$_SESSION['mailhandle']}$address\">Send Email</a>";
            else
                $email = "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>";
        }
        else
            $email = '';

        $page->setVar(array(
            'USER_ID'         => $user['id'],
            'USER_SYNERGY_ID' => $user['synergy_id'],
            'USER_NAME'       => formatName($user),
            'USER_EMAIL'      => $email,
            'USER_COUNT'      => $count['user'],
            'USER_YEAR'       => empty($user['year']) ? '' : "Year {$user['year']}",
            'USER_CAMPUS'     => $user['campus_name'],
            'USER_GROUP'      => $user['group_name'],
        ));

        $page->setVar('helpdesk_block');
        $page->setVar('timetable_block');
        $page->setVar('eportfolio_block');

        if (!$group->isStudent($user['id']) || (!$group->isStaff() && !$group->isParentOfChild($user['id'])))
            $page->setVar('due_work_block');
        else
            $page->parse('due_work_block', 'due_work');

        //Show student timetables to staff and parents of child
        if($group->isStudent($user['id'])) {
            if ($group->isStaff() || $group->isParentOfChild($user['id'])) {
                $page->parse('timetable_block', 'timetable');
                if(HELPDESK_enable)
                    $page->parse('helpdesk_block', 'helpdesk');

                //Show link to users eportfolio
                if($user['eportfolio_enabled'])
                    $page->parse('eportfolio_block', 'eportfolio');

            }
        }
        else {
            $page->setVar('helpdesk_block');
            //Show staff timetables to other staff
            if($group->isStaff() && $group->isStaff($user['id']))
                $page->parse('timetable_block', 'timetable');
        }

        if(!$_SESSION['su'])
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        $page->parse('user_block', 'user', true);
    }

    $page->parse('RESULT', 'user_t', true);
}

// render files
if (!empty($result['file'])) {
    $page->setFile('file_t', 'search/file.html');
    $page->setBlock('file_t', 'file', 'file_block');
    $page->setBlock('file', 'modify', 'modify_block');
    $page->setVar(array(
        'FILE_DISP_COUNT' => count($result['file']),
        'FILE_COUNT'      => $count['file'],
    ));

    foreach ($result['file'] as $file) {
        foreach ($categoryStd->breadcrumb($file['category']) as $i => $breadcrumb)
            $file['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'FILE_ID'          => $file['id'],
            'FILE_ICON'        => $file['iconFile'],
            'FILE_SIZE'        => bytesToString($file['file_size']),
            'FILE_DESCRIPTION' => scrubTEXT($file['description']),
            'FILE_COMMENT'     => cutSentence(scrubTEXT($file['comment']), 150),
            'FILE_OWNER'       => $file['uid'],
            'FILE_OWNER_NAME'  => formatName($file),
            'FILE_CREATED'     => getRelativeTime($file['submitted']),
            'FILE_BREADCRUMB'  => $file['breadcrumb'],
            'FILE_COUNT'       => $count['file'],
        ));
        
        if ($file['perms_type'] < 2 && $file['userAccess_type'] < 2)
            $page->setVar('modify_block');
        else
            $page->parse('modify_block', 'modify');

        $page->parse('file_block', 'file', true);
    }
    
    $page->parse('RESULT', 'file_t', true);
}

// render categories
if (!empty($result['category'])) {
    $page->setFile('category_t', 'search/category.html');
    $page->setBlock('category_t', 'category', 'category_block');
    $page->setBlock('category', 'modify', 'modify_block');
    $page->setVar(array(
        'CATEGORY_DISP_COUNT' => count($result['category']),
        'CATEGORY_COUNT'      => $count['category'],
    ));

    foreach ($result['category'] as $category) {
        foreach ($categoryStd->breadcrumb($category['id']) as $i => $breadcrumb)
            $category['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'CATEGORY_ID' => $category['id'],
            'CATEGORY_NAME' => scrubTEXT($category['name']),
            'CATEGORY_CODE' => $category['code'],
            'CATEGORY_BREADCRUMB' => $category['breadcrumb'],
            'CATEGORY_COUNT'      => $count['category'],
        ));

        if ($category['perms_type'] < 2 && $category['userAccess_type'] < 2)
            $page->setVar('modify_block');
        else
             $page->parse('modify_block', 'modify');

        $page->parse('category_block', 'category', true);
    }

    $page->parse('RESULT', 'category_t', true);
}

// render links
if (!empty($result['link'])) {
    $page->setFile('link_t', 'search/link.html');
    $page->setBlock('link_t', 'link', 'link_block');
    $page->setVar(array(
        'LINK_DISP_COUNT' => count($result['link']),
        'LINK_COUNT'      => $count['link'],
    ));


    foreach ($result['link'] as $link) {
        foreach ($categoryStd->breadcrumb($link['category']) as $i => $breadcrumb)
            $link['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'LINK_URL'         => $link['url'],
            'LINK_ICON'        => $link['iconFile'],
            'LINK_DESCRIPTION' => scrubTEXT($link['description']),
            'LINK_COMMENT'     => cutSentence(scrubTEXT($link['comment']), 150),
            'LINK_OWNER'       => $link['uid'],
            'LINK_OWNER_NAME'  => formatName($link),
            'LINK_CREATED'     => getRelativeTime($link['submitted']),
            'LINK_BREADCRUMB'  => $link['breadcrumb'],
            'LINK_COUNT'       => $count['link'],
        ));

        $page->parse('link_block', 'link', true);
    }

    $page->parse('RESULT', 'link_t', true);
}

// render news
if (!empty($result['news'])) {
    $page->setFile('news_t', 'search/news.html');
    $page->setBlock('news_t', 'news', 'news_block');
    $page->setVar(array(
        'NEWS_DISP_COUNT' => count($result['news']),
        'NEWS_COUNT'      => $count['news'],
    ));
    
    foreach ($result['news'] as $news) {
        foreach ($categoryStd->breadcrumb($news['code']) as $i => $breadcrumb)
            $news['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'NEWS_ID'         => $news['id'],
            'NEWS_CODE'       => $news['code'],
            'NEWS_HEADING'    => scrubTEXT($news['heading']),
            'NEWS_BODY'       => cutSentence(scrubTEXT(strip_tags($news['body'])), 150),
            'NEWS_OWNER'      => $news['uid'],
            'NEWS_OWNER_NAME' => formatName($news),
            'NEWS_CREATED'    => getRelativeTime($news['datetime_posted']),
            'NEWS_BREADCRUMB' => $news['breadcrumb'],
            'NEWS_COUNT'      => $count['news'],
        ));

        $page->parse('news_block', 'news', true);
    }

    $page->parse('RESULT', 'news_t', true);
}

// render blog
if (!empty($result['blog'])) {
    $page->setFile('blog_t', 'search/blog.html');
    $page->setBlock('blog_t', 'blog', 'blog_block');
    $page->setVar(array(
        'BLOG_DISP_COUNT' => count($result['blog']),
        'BLOG_COUNT'      => $count['blog'],
    ));
    
    foreach ($result['blog'] as $blog) {
        foreach ($categoryStd->breadcrumb($blog['cid']) as $i => $breadcrumb)
            $blog['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'BLOG_CID'        => $blog['cid'],
            'BLOG_HEADING'    => scrubTEXT($blog['heading']),
            'BLOG_BODY'       => cutSentence(scrubTEXT(strip_tags($blog['body'])), 150),
            'BLOG_OWNER'      => $blog['uid'],
            'BLOG_OWNER_NAME' => formatName($blog),
            'BLOG_CREATED'    => getRelativeTime($blog['created']),
            'BLOG_BREADCRUMB' => $blog['breadcrumb'],
            'BLOG_COUNT'      => $count['blog'],
        ));

        $page->parse('blog_block', 'blog', true);
    }

    $page->parse('RESULT', 'blog_t', true);
}

// render wiki
if (!empty($result['wiki'])) {
    $page->setFile('wiki_t', 'search/wiki.html');
    $page->setBlock('wiki_t', 'wiki', 'wiki_block');
    $page->setVar(array(
        'WIKI_DISP_COUNT' => count($result['wiki']),
        'WIKI_COUNT'      => $count['wiki'],
    ));

    foreach ($result['wiki'] as $wiki) {
        foreach ($categoryStd->breadcrumb($wiki['categoryID']) as $i => $breadcrumb)
            $wiki['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'WIKI_ID'         => $wiki['wikiID'],
            'WIKI_CID'        => $wiki['categoryID'],
            'WIKI_HEADING'    => scrubTEXT($wiki['heading']),
            'WIKI_BODY'       => cutSentence(scrubTEXT(strip_tags($wiki['body'])), 150),
            'WIKI_OWNER'      => $wiki['userID'],
            'WIKI_OWNER_NAME' => formatName($wiki),
            'WIKI_CREATED'    => getRelativeTime($wiki['created']),
            'WIKI_BREADCRUMB' => $wiki['breadcrumb'],
            'WIKI_COUNT'      => $count['wiki'],
        ));

        $page->parse('wiki_block', 'wiki', true);
    }
    
    $page->parse('RESULT', 'wiki_t', true);
}

// render forum
if (!empty($result['forum'])) {
    $page->setFile('forum_t', 'search/forum.html');
    $page->setBlock('forum_t', 'forum', 'forum_block');
    $page->setVar(array(
        'FORUM_DISP_COUNT' => count($result['forum']),
        'FORUM_COUNT'      => $count['forum'],
    ));

    foreach ($result['forum'] as $forum) {
        foreach ($categoryStd->breadcrumb($forum['categoryID']) as $i => $breadcrumb)
            $forum['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'FORUM_CID'        => $forum['categoryID'],
            'FORUM_THREADID'   => $forum['threadID'],
            'FORUM_TITLE'      => scrubTEXT($forum['threadTitle']),
            'FORUM_BODY'       => cutSentence(scrubTEXT(strip_tags($forum['body'])), 150),
            'FORUM_OWNER'      => $forum['uid'],
            'FORUM_OWNER_NAME' => formatName($forum),
            'FORUM_CREATED'    => getRelativeTime($forum['date']),
            'FORUM_BREADCRUMB' => $forum['breadcrumb'],
        ));

        $page->parse('forum_block', 'forum', true);
    }

    $page->parse('RESULT', 'forum_t', true);
}

if (empty($type)) {
    // clear pagination
    $page->setBlock('RESULT', 'pagination', 'pagination_block');
    $page->setVar('pagination_block');

    // execution time
    $time = microtime(true) - $time_start;

    // render final output
    $page->outputPage();
} else {
    // clear count
    $page->setBlock('RESULT', 'count', 'count_block');
    $page->setVar('count_block');

    // render pagination
    $page->setBlock('RESULT', 'pagination', 'pagination_block');

    $number_pages = ceil($count[$type] / SEARCH_type_limit);

    if ($number_pages > 1) {
        if (($current_page - 5) <= 1) {
            $start_window = 1;
            $end_window   = $number_pages > 10 ? 10 : $number_pages;
        } elseif (($current_page + 5) >= $number_pages) {
            $start_window = $number_pages - 10;
            $end_window   = $number_pages;
        } else {
            $start_window = $current_page - 5;
            $end_window   = $current_page + 5;
        }

        $page->setVar(array(
            'PAGE_NAME' => '<',
            'PAGE_NUMBER' => '1',
            'PAGE_CLASS'  => '',
        ));

        $page->parse('pagination_block', 'pagination', true);

        for ($i = $start_window; $i <= $end_window; $i++) {
            $page->setVar(array(
                'PAGE_NAME' => $i,
                'PAGE_NUMBER' => $i,
                'PAGE_CLASS'  => $i == $current_page ? 'bold' : '',
            ));

            $page->parse('pagination_block', 'pagination', true);
        }

        $page->setVar(array(
            'PAGE_NAME' => '>',
            'PAGE_NUMBER' => $number_pages,
            'PAGE_CLASS'  => '',
        ));

        $page->parse('pagination_block', 'pagination', true);
    } else {
        $page->setVar('pagination_block');
    }

    // execution time
    $time = microtime(true) - $time_start;

    // render final output
    $page->parse(TEMPLATE_body, 'RESULT', true);
    $page->outputPage(true);
}

?>
<?php
/* 
 * Global search result page
 */

// execution time
$time_start = microtime(true);

require_once '../include/sitePage.php';
require_once '../library/allowed-file.php';

require_once '../library/user.php';
require_once '../library/file.php';
require_once '../library/category.php';
require_once '../library/link.php';
require_once '../library/categoryNews.php';
require_once '../library/blog.php';
require_once '../library/wiki.php';
require_once '../library/forum.php';

$page        = new SitePage();
$allowedFile = new AllowedFile();

$keyword      = $_GET['keyword'];
$type         = $_GET['type'];
$current_page = empty($_GET['page']) ? 1 : $_GET['page'];

$config = array(
    'summary_max' => 3,
    'type_max'    => 15,
);

$handle = array(
    'user'     => new User(),
    'file'     => new File(),
    'category' => new Category(),
    'link'     => new Link(),
    'news'     => new CategoryNews(),
    'blog'     => new Blog(),
    //'wiki'     => new Wiki(),
    'forum'    => new Forum(),
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

if(empty($type)) {
    // perform search for each handle
    $page->setFile(TEMPLATE_content, 'search.html');
    $page->setBlock(TEMPLATE_content, 'type', 'type_block');
    $page->setVar($config);
    $page->setVar('KEYWORD', $keyword);

    foreach($handle as $type_name => $type_handle) {
        $result[$type_name] = $type_handle->search($keyword, 0, $config['summary_max'], $count[$type_name]);
    }

    $count['all'] = array_sum($count);

    // type list
    foreach($type_list as $type_name => $type_desc) {
        if(!$count[$type_name])
            continue;

        $page->setVar(array(
            'TYPE_COUNT' => $count[$type_name],
            'TYPE_TITLE' => $type_name,
            'TYPE_CLASS' => $count[$type_name] == 0 ? 'disabled' : '',
            'TYPE_NAME'  => $type_desc
        ));

        $page->parse('type_block', 'type', true);
    }
} elseif(!empty($handle[$type])) {
    // perform search for individual category (without formatting for ajax call)
    $result[$type] = $handle[$type]->search($keyword, ($current_page - 1) * $config['type_max'], $config['type_max'], $count[$type]);
}

// render users
if(!empty($result['user'])) {
    $page->setFile('user_t', 'search/user.html');
    $page->setBlock('user_t', 'user', 'user_block');
    $page->setVar(array(
        'USER_DISP_COUNT' => count($result['user']),
        'USER_COUNT'      => $count['user'],
    ));

    foreach($result['user'] as $user) {
        $page->setVar(array(
            'USER_ID'         => $user['id'],
            'USER_SYNERGY_ID' => $user['synergy_id'],
            'USER_NAME'       => formatName($user),
            'USER_DETAIL'     => $user['name'],
            'USER_EMAIL'      => formatEmail($user),
            'USER_COUNT'      => $count['user'],
        ));

        $page->parse('user_block', 'user', true);
    }

    $page->parse('RESULT', 'user_t', true);
}

// render files
if(!empty($result['file'])) {
    $page->setFile('file_t', 'search/file.html');
    $page->setBlock('file_t', 'file', 'file_block');
    $page->setVar(array(
        'FILE_DISP_COUNT' => count($result['file']),
        'FILE_COUNT'      => $count['file'],
    ));

    foreach($result['file'] as $file) {
        foreach($handle['category']->breadcrumb($file['category']) as $i => $breadcrumb)
            $file['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'FILE_ID'          => $file['id'],
            'FILE_ICON'        => $file['iconFile'],
            'FILE_DESCRIPTION' => scrubTEXT($file['description']),
            'FILE_COMMENT'     => cutSentence(scrubTEXT($file['comment']), 150),
            'FILE_OWNER'       => $file['uid'],
            'FILE_OWNER_NAME'  => formatName($file),
            'FILE_CREATED'     => getRelativeTime($file['submitted']),
            'FILE_BREADCRUMB'  => $file['breadcrumb'],
            'FILE_COUNT'       => $count['file'],
        ));

        $page->parse('file_block', 'file', true);
    }
    
    $page->parse('RESULT', 'file_t', true);
}

// render categories
if(!empty($result['category'])) {
    $page->setFile('category_t', 'search/category.html');
    $page->setBlock('category_t', 'category', 'category_block');
    $page->setVar(array(
        'CATEGORY_DISP_COUNT' => count($result['category']),
        'CATEGORY_COUNT'      => $count['category'],
    ));

    foreach($result['category'] as $category) {
        foreach($handle['category']->breadcrumb($category['id']) as $i => $breadcrumb)
            $category['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'CATEGORY_ID' => $category['id'],
            'CATEGORY_NAME' => scrubTEXT($category['name']),
            'CATEGORY_CODE' => $category['code'],
            'CATEGORY_BREADCRUMB' => $category['breadcrumb'],
            'CATEGORY_COUNT'      => $count['category'],
        ));

        $page->parse('category_block', 'category', true);
    }

    $page->parse('RESULT', 'category_t', true);
}

// render links
if(!empty($result['link'])) {
    $page->setFile('link_t', 'search/link.html');
    $page->setBlock('link_t', 'link', 'link_block');
    $page->setVar(array(
        'LINK_DISP_COUNT' => count($result['link']),
        'LINK_COUNT'      => $count['link'],
    ));


    foreach($result['link'] as $link) {
        foreach($handle['category']->breadcrumb($link['id']) as $i => $breadcrumb)
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
if(!empty($result['news'])) {
    $page->setFile('news_t', 'search/news.html');
    $page->setBlock('news_t', 'news', 'news_block');
    $page->setVar(array(
        'NEWS_DISP_COUNT' => count($result['news']),
        'NEWS_COUNT'      => $count['news'],
    ));
    
    foreach($result['news'] as $news) {
        foreach($handle['category']->breadcrumb($news['code']) as $i => $breadcrumb)
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
if(!empty($result['blog'])) {
    $page->setFile('blog_t', 'search/blog.html');
    $page->setBlock('blog_t', 'blog', 'blog_block');
    $page->setVar(array(
        'NEWS_DISP_COUNT' => count($result['news']),
        'NEWS_COUNT'      => $count['news'],
    ));
    
    foreach($result['blog'] as $blog) {
        foreach($handle['category']->breadcrumb($blog['cid']) as $i => $breadcrumb)
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
    
    $page->setVar(array(
        'BLOG_DISP_COUNT' => count($result['blog']),
        'BLOG_COUNT'      => $count['blog'],
    ));

    $page->parse('RESULT', 'blog_t', true);
}

// render forum
if(!empty($result['forum'])) {
    $page->setFile('forum_t', 'search/forum.html');
    $page->setBlock('forum_t', 'forum', 'forum_block');
    $page->setVar(array(
        'FORUM_DISP_COUNT' => count($result['forum']),
        'FORUM_COUNT'      => $count['forum'],
    ));

    foreach($result['forum'] as $forum) {
        foreach($handle['category']->breadcrumb($forum['categoryID']) as $i => $breadcrumb)
            $forum['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";

        $page->setVar(array(
            'FORUM_CID'        => $forum['categoryID'],
            'FORUM_THREADID'   => $forum['threadID'],
            'FORUM_TITLE'      => scrubTEXT($forum['threadTitle']),
            'FORUM_BODY'       => cutSentence(scrubTEXT(strip_tags($forum['body'])), 150),
            'FORUM_OWNER'      => $forum['uid'],
            'FORUM_OWNER_NAME' => formatName($forum),
            'FORUM_CREATED'    => getRelativeTime($forum['created']),
            'FORUM_BREADCRUMB' => $forum['breadcrumb'],
        ));

        $page->parse('forum_block', 'forum', true);
    }

    $page->parse('RESULT', 'forum_t', true);
}

if(empty($type)) {
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

    $number_pages = ceil($count[$type] / $config['type_max']);

    if($number_pages > 1) {
        if(($current_page - 5) < 1) {
            $start_window = 1;
            $end_window   = $number_pages > 10 ? 10 : $number_pages;
        } elseif(($current_page + 5) > $number_pages) {
            $start_window = $number_pages - 10;
            $end_window   = $number_pages;
        } else {
            $start_window = $current_page - 5;
            $end_window   = $current_page + 5;
        }

        for($i = $start_window; $i <= $end_window; $i++) {
            $page->setVar(array(
                'PAGE_NUMBER' => $i,
                'PAGE_CLASS'  => $i == $current_page ? 'bold' : '',
            ));

            $page->parse('pagination_block', 'pagination', true);
        }
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

<?php

require_once SITE_root . '/include/Bootstrap.php';

use \Schoolbox\Breadcrumbs;

$forumHandler = new Forum();
$groupHandler = new Group();

$this->setFile(TEMPLATE_content, 'forum/forum.html');
$this->setBlock(TEMPLATE_content, 'thread', 'thread_block');
$this->setBlock('thread', 'last_post', 'last_post_block');
$this->setBlock('thread', 'modify_thread', 'modify_thread_block');
$this->setBlock('thread', 'modified', 'modified_block');
$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

$this->setBlock(TEMPLATE_content, 'forum_post', 'forum_post_block');
$this->setBlock(TEMPLATE_content, 'modify_heading', 'modify_heading_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');

// category exists
if (is_numeric($this->arg[0])) {
    $categoryMapper = new \Schoolbox\Resource\CategoryMapper();

    try {
        $category = $categoryMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        error(2);
    }
} else {
    error(2);
}

// forum exists
if (!$forumHandler->hasForum($category->getId())) {
    SitePage::localRedir('/homepage/' . $category->getId());
}

// check permissions
if (($perm = $groupHandler->getPermission($category->getId())) < READ_ACCESS) {
    error(19);
}
//
// Parent's permissions:
// If they aren't directly involved in the forum's category, they can read but
// not write.
$is_post_allowed = !$groupHandler->hasChildAccessOnly($category->getId());

if (!$is_post_allowed && Cfg::get('restrict_parent_forum_access')) {
    error(19);
}

$this->setVar(array(
    'forum_post_block' => ''
));

if ($is_post_allowed) {
    $this->parse('forum_post_block', 'forum_post');
}

$this->setTitle(Scrub::text($category->getName()) . ' Forum');

/* output breadcrumbs */
$this->setVar(array(
    'CATEGORY' => Scrub::text($category->getName()),
    'CATEGORY_ID' => $category->getId(),
));

/*Output delete button */
$this->setVar(array(
    'delete_button_block' => '',
    'modify_heading_block' => '',
));

if ($perm >= WRITE_ACCESS) {
    $this->parse('delete_button_block', 'delete_button');
    $this->parse('modify_heading_block', 'modify_heading');
}

/* output pagination */

$threadList = $forumHandler->getThreadList($category->getId());
$pageNum = $this->arg[1];

if (!$pageNum) {
    $pageNum = 1;
}

$threadPerPage = FORUM_THREADS_PER_PAGE;
$totalPages = ceil(count($threadList) / $threadPerPage);
$threadIndex = $threadPerPage * ($pageNum - 1);

$this->setVar(array(
    'previous_button_block' => '',
    'next_button_block' => '',
));

if ($pageNum >= 2) {
    $this->parse('previous_button_block', 'previous_button');
}

if ($pageNum < $totalPages) {
    $this->parse('next_button_block', 'next_button');
}

if ($totalPages > 1) {
    for ($j = 1; $j <= $totalPages; $j++) {
        if ($pageNum == $j) {
            $this->setVar('CURRENT', ' current');
        } else {
            $this->setVar('CURRENT', '');
        }

        $this->setVar('PAGE_INDEX', $j);
        $this->parse('page_number_block', 'page_number', true);
    }
} else {
    $this->setVar('page_number_block', '');
}

$outputPagination = array(
    'PREVIOUS_PAGE' => $pageNum - 1,
    'NEXT_PAGE' => $pageNum + 1,
    'LAST_PAGE' => $totalPages,
);
$this->setVar($outputPagination);

/* output threads */
$threadList = $forumHandler->getThreadList($category->getId(), $threadIndex, $threadPerPage);

if (is_array($threadList)) {
    $userHandler = new User();
    $j = 0;

    foreach ($threadList as $thread) {
        $user = array(
            'title' => $thread['userTitle'],
            'firstname' => $thread['userFirstname'],
            'lastname' => $thread['userLastname'],
        );

        $last_post_user = array(
            'title' => $thread['last_post_title'],
            'firstname' => $thread['last_post_firstname'],
            'lastname' => $thread['last_post_surname'],
        );

        $threadTime = new Alaress_Date_Format($thread['date'], 'Y-m-d H:i:s');
        $threadLastPostTime = new Alaress_Date_Format($thread['last_post_date'], 'Y-m-d H:i:s');

        $this->setVar(array(
            'THREAD_ID' => $thread['threadID'],
            'THREAD_TITLE' => Scrub::text($thread['title']),
            'THREAD_AUTHOR' => Scrub::text($userHandler->formatName($user)),
            'THREAD_AUTHOR_ID' => $thread['userID'],
            'THREAD_AUTHOR_EXTERNAL_ID' => $thread['userSynID'],
            'THREAD_TIME' => $threadTime->getRelativeTime(),
            'THREAD_LAST_POST_TIME' => $threadLastPostTime->getRelativeTime(),
            'THREAD_LAST_POST_ID' => $thread['last_post_id'],
            'THREAD_LAST_POST_USER_EXTERNAL_ID' => $thread['last_post_external_id'],
            'THREAD_LAST_POST_USER_ID' => $thread['last_post_user_id'],
            'THREAD_LAST_POST_USER' => Scrub::text($userHandler->formatName($last_post_user)),
            'THREAD_REPLIES' => $thread['replies'],
            'THREAD_VIEWS' => $thread['views'],
            'THREAD_LAST_PAGE' => ceil((($thread['replies'] + 1) / FORUM_POSTS_PER_PAGE)),
            'THREAD_LOCKED' => $thread['locked'] == 'true' ? '(Locked)' : '',
            'ON_OFF'          => ++$j % 2 ? 'dark' : ''
        ));

        /* Delete checkbox
         * last post block
         */
        $this->setVar(array(
            'modify_thread_block' => '',
            'last_post_block' => ''
        ));

        if ($perm >= WRITE_ACCESS) {
            $this->parse('modify_thread_block', 'modify_thread', true);
        }

        if ($thread['replies'] > 0) {
            $this->parse('last_post_block', 'last_post', true);
        }

        $this->parse('thread_block', 'thread', true);
    }
} else {
    $this->setVar('thread_block', '<p>This forum is currently empty, to get started post a new thread.</p>');
}

/* Render out breadcrumbs
 */
$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $category->getName(),
        'href' => "/homepage/{$category->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Forum',
        'href' => '',
        'class' => 'active',
    ]
]);
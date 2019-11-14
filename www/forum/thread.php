<?php

use Schoolbox\Application\WebRequest\Kernel;
use \Schoolbox\Breadcrumbs;
use \Schoolbox\Mapper\Exception\Deleted as DeletedException;
use \Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$forumHandler = new Forum();
$groupHandler = new Group();

$this->setFile(TEMPLATE_content, 'forum/thread.html');
$this->setBlock(TEMPLATE_content, 'post', 'post_block');
$this->setBlock('post', 'reply_post', 'reply_post_block');
$this->setBlock('post', 'modify', 'modify_block');
$this->setBlock('post', 'delete', 'delete_block');
$this->setBlock('post', 'modified', 'modified_block');
$this->setBlock('post', 'link_start', 'link_start_block');
$this->setBlock('post', 'link_end', 'link_end_block');
$this->setBlock(TEMPLATE_content, 'reply', 'reply_block');
$this->setBlock(TEMPLATE_content, 'modify_thread', 'modify_thread_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');

/* thread exists
 */
if (is_numeric($this->arg[0])) {
    $thread = $forumHandler->getThread($this->arg[0]);
}

if (!isset($thread['threadID'])) {
    error(2);
}

$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();
try {
    $category = $categoryMapper->findById($thread['categoryID']);
} catch (DeletedException $e) {
    error(ErrorMapper::ERROR_FOLDER_DELETED);
}

/* forum exists
 */
if (!$forumHandler->hasForum($category->getId())) {
    SitePage::localRedir('/homepage/' . $category->getId());
}

/* check permissions
 */
if ($groupHandler->getPermission($category->getId()) < UserAccess::ACCESS_READ) {
    error(19);
}

$this->setTitle(Scrub::text($thread['title']));

$threadLocked = $thread['locked'] == 'true' ? true : false;

/* output breadcrumbs
 */
$output = array(
    'CATEGORY' => Scrub::text($category->getName()),
    'CATEGORY_ID' => $category->getId(),
    'THREAD_ID' => $thread['threadID'],
    'THREAD_TITLE' => Scrub::text($thread['title']),
    'LOCK_UNLOCK' => $threadLocked ? 'Unlock' : 'Lock',
    'LOCKED' => $threadLocked ? '(Locked)' : '',
);

$this->setVar($output);

$this->setVar('modify_thread_block', '');


if ($groupHandler->getPermission($category->getId()) >= UserAccess::ACCESS_WRITE) {
    $this->parse('modify_thread_block', 'modify_thread');
}

/* hide reply button if locked
 */
$this->setVar('reply_block', '');

$is_post_allowed = !$groupHandler->hasChildAccessOnly($category->getId());
if (!$is_post_allowed && Config::get('restrict_parent_forum_access')) {
    error(19);
}
if (!$threadLocked || $groupHandler->getPermission($category->getId()) >= UserAccess::ACCESS_WRITE) {
    if ($is_post_allowed) {
        $this->parse('reply_block', 'reply');
    }
}

/* record view
 */
$forumHandler->uniqueView($thread['threadID']);

/* output pagination
 */
$postList = $forumHandler->getPostList($thread['threadID']);
$pageNum = $this->arg[1];

if (!$pageNum) {
    $pageNum = 1;
}

$postPerPage = FORUM_POSTS_PER_PAGE;
$totalPages = ceil(count($postList) / $postPerPage);
$postIndex = max(0, $postPerPage * ($pageNum - 1));

$this->setVar('previous_button_block', '');
$this->setVar('next_button_block', '');

if ($pageNum >= 2) {
    $this->parse('previous_button_block', 'previous_button');
}

if ($pageNum < $totalPages) {
    $this->parse('next_button_block', 'next_button');
}

if ($totalPages > 1) {
    for ($j = 1; $j <= $totalPages; $j++) {
        if ($pageNum == $j) {
            $this->setVar('CURRENT', 'current');
        } else {
            $this->setVar('CURRENT', '');
        }

        $this->setVar('PAGE_INDEX', $j);
        $this->parse('page_number_block', 'page_number', true);
    }
} else {
    $this->setVar('page_number_block', '');
}

$outputPagination = array('PREVIOUS_PAGE' => $pageNum - 1,
                          'NEXT_PAGE' => $pageNum + 1,
                          'LAST_PAGE' => $totalPages,
);
$this->setVar($outputPagination);

/* output posts */
$postList = $forumHandler->getPostList($thread['threadID'], $postIndex, $postPerPage);
if (is_array($postList)) {
    $userHandler = new User();
    $j = 0;

// Assign first array element to variable
    $first = reset( $postList );

    foreach ($postList as $post) {
        $user = \Schoolbox\UserMapper::getInstance()->findByIdWithFallback($post['userID']);

        $postTime = new Alaress_Date_Format($post['date'], 'Y-m-d H:i:s');
        // This should be a null date, it will currently parse as now.
        // Doesn't matter much here, because this block won't be shown
        // if it is null.
        $modifiedTime = new Alaress_Date_Format($post['modified'], 'Y-m-d H:i:s');

        $output = array('POST_ID' => $post['postID'],
                        'POST_TITLE' => Scrub::text($post['title']),
                        'POST_AUTHOR' => Scrub::text($user->getFullName()),
                        'POST_GROUP' => Scrub::text($post['userGroup']),
                        'POST_TIME' => $postTime->getRelativeTime(),
                        'POST_USER_ID' => $user->getId(),
                        'POST_BODY' => Scrub::html($post['body']),
                        'POST_MODIFIED' => $modifiedTime->getRelativeTime($postTime),
                        'POST_CENSORED' => $post['censored'] == 'true' ? '(Administrator)' : '',
                        'POST_AUTHOR_EXTERNAL_ID' => $post['synergy_id'],
                        'POST_AUTHOR_NAME' => $post['firstname'] . ' ' . $post['lastname'],
                        'ON_OFF'          => ++$j % 2 ? 'dark' : '',
        );
        $this->setVar($output);

        //Remove link for deleted user
        if (!$user->getDeletedAt()->isNull()) {
            $this->setVar('link_start_block', '');
            $this->setVar('link_end_block', '');
        } else {
            $this->parse('link_start_block', 'link_start');
            $this->parse('link_end_block', 'link_end');
        }

        // edit post
        $this->setVar('modify_block', '');

        $this->setVar('modify_heading_block', '');

        $this->setVar('delete_block', '');

        if ((!$threadLocked && $post['userID'] == $_SESSION['id']) || $groupHandler->getPermission(
            $category->getId()
        ) >= UserAccess::ACCESS_WRITE) {
            $this->parse('modify_block', 'modify');

            // delete post
            if ($post !== $first) {
                $this->parse('delete_block', 'delete');
            }
        }

        // edited time
        $this->setVar('modified_block', '');
        if ($post['modified'] > 0 && $post['modified'] != $post['date']) {
            $this->parse('modified_block', 'modified');
        }

        // reply to this post
        $this->setVar('reply_post_block', '');
        if (!$threadLocked || $groupHandler->getPermission($category->getId()) >= UserAccess::ACCESS_WRITE) {
            if ($is_post_allowed) {
                $this->parse('reply_post_block', 'reply_post');
            }
        }

        $this->parse('post_block', 'post', true);
    }

} else {
    $this->setVar('post_block', '<p>There are no posts to display. Create a new post.</p>');
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
        'href' => "/forum/{$category->getId()}",
        'class' => '',
    ],
    [
        'text' => $thread['title'],
        'href' => '',
        'class' => 'active',
    ]
]);

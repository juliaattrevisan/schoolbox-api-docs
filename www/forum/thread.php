<?php

use Schoolbox\Breadcrumbs;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception\Deleted as DeletedException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;

if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

$forumHandler = new Forum();
$groupHandler = new Group();
$access = Access::getInstance();

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
    try {
        /** @var \Schoolbox\Forum\Thread $thread */
        $thread = ThreadMapper::getInstance()->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_POST_NOT_FOUND);
    } catch (Exception $e) {
        error(ErrorMapper::ERROR_INFORMATION_MISSING);
    }
}

if ($thread->getId() === null) {
    error(2);
}

if ($thread->getCategory() === false ||  $thread->getCategory()->isDeleted() === true) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FOLDER_DELETED);
} else {
    //$folder is (old)$category
    /** @var  FolderMapper $folder */
    $folder = FolderMapper::getInstance()->findById($thread->getCategory()->getId());
}

/* forum exists
 */
// TODO: ThreadMapper->hasForum
//if (!$thread->hasForum($folder->getId())) {
if (!$forumHandler->hasForum($folder->getId())) {
    SitePage::localRedir('/homepage/' . $folder->getId());
}

/* check permissions
 */
$accessLevel = $access->getFolderAccess($_SESSION['user'], $folder->getId());
if ($accessLevel < UserAccess::ACCESS_READ) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$this->setTitle(Scrub::text($thread->getTitle()));

/* output breadcrumbs
 */
$output = array(
    'CATEGORY' => Scrub::text($folder->getName()),
    'CATEGORY_ID' => $folder->getId(),
    'THREAD_ID' => $thread->getId(),
    'THREAD_TITLE' => Scrub::text($thread->getTitle()),
    'LOCK_UNLOCK' => $threadLocked ? 'Unlock' : 'Lock',
    'LOCKED' => $thread->isLocked() ? '(Locked)' : '',
);

$this->setVar($output);

$this->setVar('modify_thread_block', '');



$accessLevel = $access->getFolderAccess($_SESSION['user'], $folder->getId());
if ($accessLevel <= UserAccess::ACCESS_WRITE) {
    $this->parse('modify_thread_block', 'modify_thread');
}

/* hide reply button if locked
 */
$this->setVar('reply_block', '');

$is_post_allowed = !$groupHandler->hasChildAccessOnly($folder->getId());
if (!$is_post_allowed && Config::get('restrict_parent_forum_access')) {
    error(19);
}
if (!$threadLocked || $groupHandler->getPermission($folder->getId()) >= UserAccess::ACCESS_WRITE) {
    if ($is_post_allowed) {
        $this->parse('reply_block', 'reply');
    }
}

/* record view
 */
//TODO: ThreadMapper->uniqueView
$forumHandler->uniqueView($thread->getId());

/* output pagination
 */
$postList = $thread->getPosts();
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
//TODO: ThreadMapper->getPostList($threadID, $index = 0, $limit = 0)
//$postList = $forumHandler->getPostList($thread->getId(), $postIndex, $postPerPage);
$postList = $thread->getPosts();

if ($postList !== null) {
    $userHandler = new User();
    $j = 0;

// Assign first array element to variable
    $first = reset($postList);

    foreach ($postList as $post) {
//        $user = \Schoolbox\UserMapper::getInstance()->findByIdWithFallback($post['userID']);
//        $user = $post->getOwner();

//        $postTime = new Alaress_Date_Format($post['date'], 'Y-m-d H:i:s');
        // This should be a null date, it will currently parse as now.
        // Doesn't matter much here, because this block won't be shown
        // if it is null.
//        $modifiedTime = new Alaress_Date_Format($post['modified'], 'Y-m-d H:i:s');

        $output = array('POST_ID' => $post->getId(),
//                        'POST_TITLE' => Scrub::text($post->getTitle()),
                        'POST_AUTHOR' => Scrub::text($post->getOwner()->getFullName()),
//                        'POST_GROUP' => Scrub::text($post['userGroup']),
                        'POST_TIME' => $post->getDate()->getRelativeTime(),
                        'POST_USER_ID' => $post->getOwner()->getId(),
                        'POST_BODY' => Scrub::html($post->getBody()),
                        'POST_MODIFIED' => $post->getModified()->getRelativeTime(),
                        'POST_CENSORED' => $post->getCensored() == 'true' ? '(Administrator)' : '',
//                        'POST_AUTHOR_EXTERNAL_ID' => $post->get['synergy_id'],
                        'POST_AUTHOR_NAME' => $post->getOwner()->getFullName(),
                        'ON_OFF'          => ++$j % 2 ? 'dark' : '',
        );
        $this->setVar($output);

        //Remove link for deleted user
        if ($post->getOwner()->getDeletedAt()) {
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

        if (
            (!$threadLocked && $post->getOwner()->getId() == $_SESSION['id']) || $groupHandler->getPermission(
                $folder->getId()
            ) >= UserAccess::ACCESS_WRITE
        ) {
            $this->parse('modify_block', 'modify');

            // delete post
            if ($post !== $first) {
                $this->parse('delete_block', 'delete');
            }
        }

        // edited time
        $this->setVar('modified_block', '');
        if ($post->getModified() > 0 && $post->getModified() != $post->getDate()) {
            $this->parse('modified_block', 'modified');
        }

        // reply to this post
        $this->setVar('reply_post_block', '');
        if (!$threadLocked || $groupHandler->getPermission($folder->getId()) >= UserAccess::ACCESS_WRITE) {
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
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Forum',
        'href' => "/forum/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => $thread->getTitle(),
        'href' => '',
        'class' => 'active',
    ]
]);

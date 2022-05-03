<?php

use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;

$threadMapper = \Schoolbox\Forum\ThreadMapper::getInstance();
$groupHandler = new Group();

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'forum/forum.html');
$this->setBlock(TEMPLATE_content, 'thread', 'thread_block');
$this->setBlock('thread', 'last_post', 'last_post_block');
$this->setBlock('thread', 'modify_thread', 'modify_thread_block');
$this->setBlock('thread', 'modified', 'modified_block');
$this->setBlock('thread', 'thread_author', 'thread_author_block');
$this->setBlock('thread', 'deleted_thread_author', 'deleted_thread_author_block');
$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

$this->setBlock(TEMPLATE_content, 'no_thread', 'no_thread_block');
$this->setBlock('no_thread', 'no_post', 'no_post_block');

$this->setBlock(TEMPLATE_content, 'forum_post', 'forum_post_block');
$this->setBlock(TEMPLATE_content, 'modify_heading', 'modify_heading_block');
$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');

// category exists
if (is_numeric($this->arg[0] ?? null)) {
    $categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

    try {
        $category = $categoryMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }
} else {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

// forum exists
if (!$threadMapper->isComponent($category->getId())) {
    SitePage::localRedir('/homepage/' . $category->getId());
}

// check permissions
if (($perm = $groupHandler->getPermission($category->getId())) < UserAccess::ACCESS_READ) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
//
// Parent's permissions:
// If they aren't directly involved in the forum's category, they can read but
// not write.
$is_post_allowed = !$groupHandler->hasChildAccessOnly($category->getId());

if (!$is_post_allowed && Config::get('restrict_parent_forum_access')) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
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

if ($perm >= UserAccess::ACCESS_WRITE) {
    $this->parse('delete_button_block', 'delete_button');
    $this->parse('modify_heading_block', 'modify_heading');
}

/* output pagination */

$threadList = $threadMapper->findByColumn('categoryID', $category->getId())->getAll();
$pageNum = $this->arg[1] ?? 1;

if (!$pageNum) {
    $pageNum = 1;
}

$threadPerPage = \Schoolbox\Forum\ThreadMapper::FORUM_THREADS_PER_PAGE;
$totalPages = count($threadList) ? ceil(count($threadList) / $threadPerPage) : 0;
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
/**
 * limited, offset, and ordered correctly... (by the most recent thread that was updated)
 */
$threadList = $threadMapper->findByColumn(
    'categoryID',
    $category->getId(),
    [
        'limit' => $threadPerPage,
        'offset' => $threadIndex,
        'order' => [
            'last_post_date' => 'DESC'
        ]
    ]
)->getAll();

if (0 !== count($threadList)) {
    $userHandler = new User();
    $j = 0;

    foreach ($threadList as $thread) {
        $this->setVar(array(
            'THREAD_ID' => $thread->getId(),
            'THREAD_TITLE' => Scrub::text($thread->getTitle()),
            'THREAD_AUTHOR' => Scrub::text($thread->getUser()->getFullname()),
            'THREAD_AUTHOR_ID' => $thread->getUser()->getId(),
            'THREAD_AUTHOR_EXTERNAL_ID' => $thread->getUser()->getExternalId(),
            'THREAD_TIME' => $thread->getDate()->getRelativeTime(),
            'THREAD_LAST_POST_TIME' => $thread->getLastPostDate()->getRelativeTime(),
            'THREAD_LAST_POST_ID' => $thread->getLastPost()->getId(),
            'THREAD_LAST_POST_USER_EXTERNAL_ID' => $thread->getLastPostUser()->getExternalId(),
            'THREAD_LAST_POST_USER_ID' => $thread->getLastPostUser()->getId(),
            'THREAD_LAST_POST_USER' => $thread->getLastPostUser()->getFullname(),
            'THREAD_REPLIES' => $thread->getPosts()->count(),
            'THREAD_VIEWS' => $thread->getViews(),
            'THREAD_LAST_PAGE' => ceil((($thread->getPosts()->count() + 1) / \Schoolbox\Forum\ThreadMapper::FORUM_THREADS_PER_PAGE)),
            'THREAD_LOCKED' => $thread->isLocked() == 'true' ? '(Locked)' : '',
            'ON_OFF'          => ++$j % 2 ? 'dark' : ''
        ));

        /* Delete checkbox
         * last post block
         */
        $this->setVar(array(
            'modify_thread_block' => '',
            'last_post_block' => ''
        ));

        if ($perm >= UserAccess::ACCESS_WRITE) {
            $this->parse('modify_thread_block', 'modify_thread', true);
        }

        if ($thread->getPosts()->count() > 0) {
            $this->parse('last_post_block', 'last_post', true);
        }

        $this->setVar('deleted_thread_author_block', '');
        $this->parse('thread_author_block', 'thread_author');

        if ($thread->getUser()->isDeleted()) {
            $this->setVar('thread_author_block', '');
            $this->parse('deleted_thread_author_block', 'deleted_thread_author');
        }

        $this->parse('thread_block', 'thread', true);
    }

    $this->setVar(array(
        'no_thread_block' => ''
    ));
} else {
    $this->parse('no_thread_block', 'no_thread');
    $this->setVar(array(
        'thread_block' => '',
        'delete_button_block' => '',
        'no_post_block' => '',
    ));

    if ($is_post_allowed) {
        $this->parse('no_post_block', 'no_post');
    }
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

<?php

use Schoolbox\Breadcrumbs;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Forum\PostMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Mapper\Exception\Deleted as DeletedException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;

if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

$groupHandler = new Group(); //only single call, still working out how to do the hasChildAccessOnly
$access = Access::getInstance();
$threadMapper = ThreadMapper::getInstance();

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
        $thread = $threadMapper->findById($this->arg[0]);
    } catch (DeletedException $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_POST_NOT_FOUND);
    } catch (Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }
}

if ($thread->getId() === null) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

if ($thread->getCategory() === false ||  $thread->getCategory()->isDeleted() === true) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FOLDER_DELETED);
} else {
    //$folder is (old)$category
    /** @var  FolderModel $folder */
    $folder = FolderMapper::getInstance()->findById($thread->getCategory()->getId());
}

/* forum exists
 */
if (!$threadMapper->isComponent($folder->getId())) {
    SitePage::localRedir('/homepage/' . $folder->getId());
}

/* check permissions
 */
$accessLevel = $access->getFolderAccess($_SESSION['user'], $folder->getId());
if ($accessLevel < UserAccess::ACCESS_READ) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$this->setTitle(Scrub::text($thread->getTitle()));

/* output breadcrumbs
 */
$output = array(
    'CATEGORY' => Scrub::text($folder->getName()),
    'CATEGORY_ID' => $folder->getId(),
    'THREAD_ID' => $thread->getId(),
    'THREAD_TITLE' => Scrub::text($thread->getTitle()),
    'LOCK_UNLOCK' => $thread->isLocked() ? 'Unlock' : 'Lock',
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

//TODO: replace $groupHandler
$is_post_allowed = !$groupHandler->hasChildAccessOnly($folder->getId());
if (!$is_post_allowed && ConfigLoader::getInstance()->get('restrict_parent_forum_access')) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
if (!$thread->isLocked() || $access->getFolderAccess($_SESSION['user'], $folder->getId()) >= UserAccess::ACCESS_WRITE) {
    if ($is_post_allowed) {
        $this->parse('reply_block', 'reply');
    }
}

/* record view
 */
$thread->recordView();
$threadMapper->save($thread);

/* output pagination
 */
$postList = $thread->getPosts();
$pageNum = $this->arg[1];

if (!$pageNum) {
    $pageNum = 1;
}

//dragged across from Forum.php what is the better way for this? put in db config table?
$postPerPage = 15;
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
$postList = $postList->slice($postIndex, $postPerPage);

if ($postList !== null) {
    $first = $postList->first();
    $j = 0;

    foreach ($postList as $postObj) {
        /**  hydrate the posts. need to do some further investigating into where this could be added to the initial call of get thread.
         *
         * when I just use $postObj and attempt to grab the owner name those fields default to null (some of the time)
         * so it looks like it has hydrated the forum, post, owner.ID and that is all, but I need the owner full name also.
        */
        $post = PostMapper::getInstance()->findById($postObj->getId());

        $output = array('POST_ID' => $post->getId(),
                        'POST_AUTHOR' => Scrub::text($post->getOwner()->getFullName()),
                        'POST_TIME' => $post->getDate()->getRelativeTime(),
                        'POST_USER_ID' => $post->getOwner()->getId(),
                        'POST_BODY' => Scrub::html($post->getBody()),
                        'POST_MODIFIED' => $post->getModified()->getRelativeTime(),
                        'POST_CENSORED' => $post->getCensored() == 'true' ? '(Administrator)' : '',
                        'POST_AUTHOR_EXTERNAL_ID' => $post->getOwner()->getExternalId(),
                        'POST_AUTHOR_NAME' => $post->getOwner()->getFullName(),
                        'ON_OFF'          => ++$j % 2 ? 'dark' : '',
        );
        $this->setVar($output);

        //Remove link for deleted user
        if ($post->getOwner()->getEnabled()) {
            $this->parse('link_start_block', 'link_start');
            $this->parse('link_end_block', 'link_end');
        } else {
            $this->setVar('link_start_block', '');
            $this->setVar('link_end_block', '');
        }

        // edit post
        $this->setVar('modify_block', '');

        $this->setVar('modify_heading_block', '');

        $this->setVar('delete_block', '');

        if (
            (!$thread->isLocked() && $post->getOwner()->getId() == $_SESSION['id']) || ($access->getFolderAccess($_SESSION['user'], $folder->getId()) >= UserAccess::ACCESS_WRITE)
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
        if (!$thread->isLocked() || ($access->getFolderAccess($_SESSION['user'], $folder->getId()) >= UserAccess::ACCESS_WRITE)) {
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

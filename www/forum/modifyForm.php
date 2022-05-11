<?php

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Forum\PostMapper;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group();
$access = Access::getInstance();
$threadMapper = ThreadMapper::getInstance();
$postMapper = PostMapper::getInstance();

if (is_numeric($_POST['categoryID'] ?? false)) {
    /* Category exists
     */
    try {
        $folder = FolderMapper::getInstance()->findById($_POST['categoryID']);
    } catch (Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }

    // forum exists
    if (!$threadMapper->isComponent($folder->getId())) {
        SitePage::localRedir('/homepage/' . $folder->getId());
    }

    /* check permissions
     */
    $accessLevel = $access->getFolderAccess($_SESSION['user'], $folder->getId());
    if ($accessLevel < UserAccess::ACCESS_READ) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
    if ($groupHandler->hasChildAccessOnly($_POST['categoryID'], $_SESSION['user']->getId())) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    /* modify post
     */
    if (is_numeric($_POST['postID'])) {
        try {
            $post = $postMapper->findById($_POST['postID']);
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_POST_NOT_FOUND);
        } catch (Exception $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }


        /* check user owns post
         */
        /** @var \Schoolbox\Forum\Post $post */
        if ($post->getOwner()->getId() != $_SESSION['id'] && $accessLevel < UserAccess::ACCESS_WRITE) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        try {
            $thread = $threadMapper->findById($post->getThread()->getId());
        } catch (Exception $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        /* check locked
         */
        /** @var \Schoolbox\Forum\Thread $thread */
        if ($thread->isLocked() && $accessLevel < UserAccess::ACCESS_WRITE) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        $threadMapper->getDatabase()->beginTransaction();
        /* modify thread
         */
        if ($thread->getFirstPost()->getId() == $post->getId()) {
            $thread->setTitle($_POST['title']);

            try {
                $threadMapper->save($thread);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $threadMapper->getDatabase()->rollback();
                throw ErrorMapper::getInstance()->generateException($e->getCode(), $e->getMessage());
            }
        }

        /* modify post
         */

        $post->setBody($_POST['body']);

        try {
            $postMapper->save($post);
            $threadMapper->getDatabase()->commit();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $threadMapper->getDatabase()->rollback();
            throw ErrorMapper::getInstance()->generateException($e->getCode(), $e->getMessage());
        }
    } else {
        /* create thread
         */
        $threadMapper->getDatabase()->beginTransaction();
        if (!is_numeric($_POST['threadID'])) {
            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = $threadMapper->createModel();
            $thread->setCategory(FolderMapper::getInstance()->createModel()->setId($_POST['categoryID']));
            $thread->setUser($_SESSION['user']);
            $thread->setTitle($_POST['title']);

            try {
                $threadMapper->save($thread);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $threadMapper->getDatabase()->rollback();
                throw ErrorMapper::getInstance()->generateException($e->getCode(), $e->getMessage());
            }
        } else {
            try {
                $thread = $threadMapper->findById($_POST['threadID']);
            } catch (Exception $e) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
            }

            /* check locked
             */
            /** @var \Schoolbox\Forum\Thread $thread */
            if ($accessLevel < UserAccess::ACCESS_WRITE && $thread->isLocked()) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
            }
        }

        /**
         * create post
         * @var \Schoolbox\Forum\Post $post
         */
        $post = $postMapper->createModel();
        $post->setOwner($_SESSION['user']);
        $post->setBody($_POST['body']);
        $post->setThread($thread);

        if ($_POST['quoteID']) {
            $post->setParent($postMapper->createModel()->setId($_POST['quoteID']));
        }

        try {
            $postMapper->save($post);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $threadMapper->getDatabase()->rollback();
            throw ErrorMapper::getInstance()->generateException($e->getCode(), $e->getMessage());
        }

        /* update thread */
        $thread->setLastPostUser($_SESSION['user']);  //this probably should be deprecated can be retrieved from post ID
        $thread->setLastPostDate(Alaress_Date_Format::now()); //this probably should be deprecated can be retrieved from post ID
        $thread->setLastPost($post);
        try {
            $threadMapper->save($thread);
            $threadMapper->getDatabase()->commit();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $threadMapper->getDatabase()->rollback();
            throw ErrorMapper::getInstance()->generateException($e->getCode(), $e->getMessage());
        }
    }
    SitePage::localRedir('/forum/thread/' . $thread->getId() . '/' . $postMapper->getPageNumber($post) . '/#' . $post->getId());
} elseif (is_numeric($_GET['toggleLock'] ?? false)) {
    /* thread exists
     */
    try {
        $thread = $threadMapper->findById($_GET['toggleLock']);
    } catch (Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }

    /* forum exists
     */
    /** @var \Schoolbox\Forum\Thread $thread */
    if (!$threadMapper->isComponent($thread->getCategory()->getId())) {
        SitePage::localRedir('/homepage/' . $thread->getCategory()->getId());
    }

    /* check permissions
     */
    $accessLevel = $access->getFolderAccess($_SESSION['user'], $thread->getCategory()->getId());
    if ($accessLevel < UserAccess::ACCESS_WRITE) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
    if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId(), $_SESSION['user']->getId())) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    $thread->setLocked(!$thread->isLocked());

    $threadMapper->save($thread);

    $page->backToTheFuture(1);
} elseif (is_numeric($_GET['deleteThread'])) {
    /* thread exists
     */
    try {
        $thread = $threadMapper->findById($_GET['deleteThread']);
    } catch (Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }

    /* forum exists
     */
    /** @var \Schoolbox\Forum\Thread $thread */
    if (!$threadMapper->isComponent($thread->getCategory()->getId())) {
        SitePage::localRedir('/homepage/' . $thread->getCategory()->getId());
    }

    /* check permissions
     */
    $accessLevel = $access->getFolderAccess($_SESSION['user'], $thread->getCategory()->getId());
    if ($accessLevel < UserAccess::ACCESS_WRITE) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
    if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId(), $_SESSION['user']->getId())) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    $threadMapper->delete($thread);

    SitePage::localRedir('/forum/' . $thread->getCategory()->getId());
} elseif (is_array($_POST['deleteThreads'])) {
    foreach ($_POST['deleteThreads'] as $threadID) {
        /* thread exists
         */
        try {
            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = $threadMapper->findById($threadID);

            /* check permissions
             */
            $accessLevel = $access->getFolderAccess($_SESSION['user'], $thread->getCategory()->getId());
            if ($accessLevel < UserAccess::ACCESS_WRITE) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
            }
            if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId(), $_SESSION['user']->getId())) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
            }

            $categoryID = $thread->getCategory()->getId();

            $threadMapper->delete($thread);
        } catch (Exception $e) {
        }
    }

    SitePage::localRedir('/forum/' . $categoryID);
} elseif (is_numeric($_GET['deletePost'])) {
    /* post exists
     */
    try {
        $post   = $postMapper->findById($_GET['deletePost']);

        $thread = $threadMapper->findById($post->getThread()->getId());

        $earliestPost  = $postMapper->findFirstPost($post->getThread()->getId());
    } catch (Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }


    /* check permissions
     */
    /** @var \Schoolbox\Forum\Thread $thread */
    $accessLevel = $access->getFolderAccess($_SESSION['user'], $thread->getCategory()->getId());
    /** @var \Schoolbox\Forum\Post $post */
    if ($accessLevel < UserAccess::ACCESS_WRITE && $post->getOwner()->getId() !== $_SESSION['user']->getId()) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    /* check locked
    */
    if ($accessLevel < UserAccess::ACCESS_WRITE && $thread->isLocked()) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
    if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId(), $_SESSION['user']->getId())) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    $pageNumber = $postMapper->getPageNumber($post);

    /** @var \Schoolbox\Forum\Thread $earliestPost */
    if ($earliestPost->getId() !== $post->getId()) {
        $postMapper->delete($post);
    } else {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FORUM_FIRST_POST_CANNOT_BE_DELETED);
    }

    SitePage::localRedir('/forum/thread/' . $post->getThread()->getId() . '/' . $pageNumber);
} else {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}

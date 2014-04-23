<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use \Schoolbox\Resource\CategoryMapper;
use \Schoolbox\Forum\PostMapper;
use \Schoolbox\Forum\ThreadMapper;

$page = new SitePage();
$forumHandler = new Forum();
$groupHandler = new Group();

if (is_numeric($_POST['categoryID'])) {

	/* Category exists
	 */
    try {
        $folder = CategoryMapper::getInstance()->findById($_POST['categoryID']);
    }
    catch (Exception $e) {
        error(2);
    }

	// forum exists
	if (!$forumHandler->hasForum($folder->getId())) {
		SitePage::localRedir('/homepage/' . $folder->getId());
	}

	/* check permissions
	 */
	if (($permission = $groupHandler->getPermission($_POST['categoryID'])) < READ_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($_POST['categoryID'])) {
		error(19);
	}

	/* modify post
	 */
	if (is_numeric($_POST['postID'])) {

        try{
            $post = PostMapper::getInstance()->findById($_POST['postID']);
        } catch (Exception $e) {
            error(2);
        }


		/* check user owns post
		 */
		if ($post->getOwner()->getId() != $_SESSION['id'] && $permission < WRITE_ACCESS) {
			error(19);
		}

        try {
            $thread = ThreadMapper::getInstance()->findById($post->getThreadId());
        } catch (Exception $e) {
            error(2);
        }

		/* check locked
		 */
		if ($thread->isLocked() && $permission < WRITE_ACCESS) {
			error(19);
		}

		/* modify thread
		 */
		if ($thread->getFirstPost()->getPostId() == $post->getPostId()) {

            $thread->setTitle($_POST['title']);

            ThreadMapper::getInstance()->save($thread);
		}

		/* modify post
		 */

        $post->setOwner($_SESSION['user']);
        $post->setBody($_POST['body']);

        PostMapper::getInstance()->save($post);
	}
	else {
		/* create thread
		 */
		if (!is_numeric($_POST['threadID'])) {
            $thread = ThreadMapper::getInstance()->createModel();
            $thread->setCategoryID($_POST['categoryID']);
		    $thread->setUserID($_SESSION['id']);
			$thread->setTitle($_POST['title']);

            ThreadMapper::getInstance()->save($thread);
		}
		else {
            try {
                $thread = ThreadMapper::getInstance()->findById($_POST['threadID']);
            } catch (Exception $e) {
                error(2);
            }

			/* check locked
			 */
			if ($thread->isLocked() && $permission < WRITE_ACCESS) {
				error(19);
			}
		}

		/* create post
		 */
        $post = PostMapper::getInstance()->createModel();
        $post->setOwner($_SESSION['user']);
        $post->setBody($_POST['body']);
        $post->setThreadId($thread->getThreadID());
        PostMapper::getInstance()->save($post);

        /* update thread */
        $thread->setLastPostUser($_SESSION['id']);  //this probably should be deprecated can be retrieved from post ID
        $thread->setLastPostDate(new DateTime('now')); //this probably should be deprecated can be retrieved from post ID
        $thread->setLastPost($post);
        ThreadMapper::getInstance()->save($thread);

   	}

	SitePage::localRedir('/forum/thread/' . $thread->getThreadId() . '/' . $forumHandler->getPageNumber($post->getPostId()) . '/#' . $post->getPostId());
}
else if (is_numeric($_GET['toggleLock'])) {

	/* thread exists
	 */
    try {
        $thread = ThreadMapper::getInstance()->findById($_GET['toggleLock']);
    } catch (Exception $e) {
        error(2);
    }

	/* forum exists
	 */
	if (!$forumHandler->hasForum($thread->getCategoryID())) {
		SitePage::localRedir('/homepage/' . $thread->getCategoryID());
	}

	/* check permissions
	 */
	if ($groupHandler->getPermission($thread->getCategoryID()) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread->getCategoryID())) {
		error(19);
	}

    $thread->setLocked($thread->isLocked()?false:true);

    ThreadMapper::getInstance()->save($thread);

	$page->backToTheFuture(1);
}
else if (is_numeric($_GET['deleteThread'])) {

	/* thread exists
	 */
    try {
        $thread = ThreadMapper::getInstance()->findById($_GET['deleteThread']);
    } catch (Exception $e) {
        error(2);
    }

	/* forum exists
	 */
	if (!$forumHandler->hasForum($thread->getCategoryID())) {
		SitePage::localRedir('/homepage/' . $thread->getCategoryID());
	}

	/* check permissions
	 */
	if ($groupHandler->getPermission($thread->getCategoryID()) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread->getCategoryID())) {
		error(19);
	}

	$forumHandler->delete($thread->getThreadID());

	SitePage::localRedir('/forum/' . $thread->getCategoryID());
}
else if (is_array($_POST['deleteThreads'])) {
	foreach ($_POST['deleteThreads'] as $threadID) {
		/* thread exists
		 */
        try {
            $thread = ThreadMapper::getInstance()->findById($threadID);

			/* check permissions
			 */
			if ($groupHandler->getPermission($thread->getCategoryID()) < WRITE_ACCESS) {
				error(19);
			}
			if ($groupHandler->hasChildAccessOnly($thread->getCategoryID())) {
				error(19);
			}

            $categoryID = $thread->getCategoryID();

            ThreadMapper::getInstance()->delete($thread);
        } catch (Exception $e) { }
	}

	SitePage::localRedir('/forum/' . $categoryID);
}
else if (is_numeric($_GET['deletePost'])) {

	/* post exists
	 */
    try{
        $post = PostMapper::getInstance()->findById($_GET['deletePost']);
        $thread = ThreadMapper::getInstance()->findById($post->getThreadID());
    } catch (Exception $e) {
        error(2);
    }

	/* check locked
	 */
	if ($thread->isLocked() && $groupHandler->getPermission($thread->getCategoryId()) < WRITE_ACCESS) {
		error(19);
	}

	/* check permissions
	 */
	if ($post->getOwner()->getId() != $_SESSION['id'] && $groupHandler->getPermission($thread->getCategoryId()) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread->getCategoryId())) {
		error(19);
	}

	$pageNumber = $forumHandler->getPageNumber($post->getPostID());

    PostMapper::getInstance()->delete($post);

	SitePage::localRedir('/forum/thread/' . $post->getThreadId() . '/' . $pageNumber);
}
else {
	error(2);
}

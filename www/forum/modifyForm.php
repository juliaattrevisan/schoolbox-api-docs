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

        try {
            /** @var \Schoolbox\Forum\Post $post */
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
            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = ThreadMapper::getInstance()->findById($post->getThread()->getId());
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
		if ($thread->getFirstPost()->getId() == $post->getId()) {

            $thread->setTitle($_POST['title']);

            ThreadMapper::getInstance()->save($thread);
		}

		/* modify post
		 */

        $post->setOwner($_SESSION['user']);
        $post->setBody(str_replace('<p>&nbsp;</p>', '', $_POST['body']));

        PostMapper::getInstance()->save($post);
	}
	else {
		/* create thread
		 */
		if (!is_numeric($_POST['threadID'])) {
            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = ThreadMapper::getInstance()->createModel();
            $thread->setCategory(CategoryMapper::getInstance()->createModel()->setId($_POST['categoryID']));
		    $thread->setUser($_SESSION['user']);
			$thread->setTitle($_POST['title']);

            ThreadMapper::getInstance()->save($thread);
		}
		else {
            try {
                /** @var \Schoolbox\Forum\Thread $thread */
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

        /**
         * create post
         * @var \Schoolbox\Forum\Post $post
         */
        $post = PostMapper::getInstance()->createModel();
        $post->setOwner($_SESSION['user']);
        $post->setBody(str_replace('<p>&nbsp;</p>', '', $_POST['body']));
        $post->setThread($thread);

        if ($_POST['quoteID']) {
            $post->setParent(PostMapper::getInstance()->createModel()->setId($_POST['quoteID']));
        }

        PostMapper::getInstance()->save($post);

        /* update thread */
        $thread->setLastPostUser($_SESSION['user']);  //this probably should be deprecated can be retrieved from post ID
        $thread->setLastPostDate(Alaress_Date_Format::now()); //this probably should be deprecated can be retrieved from post ID
        $thread->setLastPost($post);
        ThreadMapper::getInstance()->save($thread);

   	}

	SitePage::localRedir('/forum/thread/' . $thread->getId() . '/' . $forumHandler->getPageNumber($post->getId()) . '/#' . $post->getId());
}
else if (is_numeric($_GET['toggleLock'])) {

	/* thread exists
	 */
    try {
        /** @var \Schoolbox\Forum\Thread $thread */
        $thread = ThreadMapper::getInstance()->findById($_GET['toggleLock']);
    } catch (Exception $e) {
        error(2);
    }

	/* forum exists
	 */
	if (!$forumHandler->hasForum($thread->getCategory()->getId())) {
		SitePage::localRedir('/homepage/' . $thread->getCategory()->getId());
	}

	/* check permissions
	 */
	if ($groupHandler->getPermission($thread->getCategory()->getId()) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId())) {
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
        /** @var \Schoolbox\Forum\Thread $thread */
        $thread = ThreadMapper::getInstance()->findById($_GET['deleteThread']);
    } catch (Exception $e) {
        error(2);
    }

	/* forum exists
	 */
	if (!$forumHandler->hasForum($thread->getCategory()->getId())) {
		SitePage::localRedir('/homepage/' . $thread->getCategory()->getId());
	}

	/* check permissions
	 */
	if ($groupHandler->getPermission($thread->getCategory()->getId()) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId())) {
		error(19);
	}

	$forumHandler->delete($thread->getId());

	SitePage::localRedir('/forum/' . $thread->getCategory()->getId());
}
else if (is_array($_POST['deleteThreads'])) {
	foreach ($_POST['deleteThreads'] as $threadID) {
		/* thread exists
		 */
        try {
            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = ThreadMapper::getInstance()->findById($threadID);

			/* check permissions
			 */
			if ($groupHandler->getPermission($thread->getCategory()->getId()) < WRITE_ACCESS) {
				error(19);
			}
			if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId())) {
				error(19);
			}

            $categoryID = $thread->getCategory()->getId();

            ThreadMapper::getInstance()->delete($thread);
        } catch (Exception $e) { }
	}

	SitePage::localRedir('/forum/' . $categoryID);
}
else if (is_numeric($_GET['deletePost'])) {

	/* post exists
	 */
    try {
        /** @var \Schoolbox\Forum\Post $post */
        $post   = PostMapper::getInstance()->findById($_GET['deletePost']);

        /** @var \Schoolbox\Forum\Thread $thread */
        $thread = ThreadMapper::getInstance()->findById($post->getThread()->getId());
    } catch (Exception $e) {
        error(2);
    }

	/* check locked
	 */
	if ($thread->isLocked() && $groupHandler->getPermission($thread->getCategory()->getId()) < WRITE_ACCESS) {
		error(19);
	}

	/* check permissions
	 */
	if ($post->getOwner()->getId() != $_SESSION['id'] && $groupHandler->getPermission($thread->getCategory()->getId()) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread->getCategory()->getId())) {
		error(19);
	}

	$pageNumber = $forumHandler->getPageNumber($post->getId());

    PostMapper::getInstance()->delete($post);

	SitePage::localRedir('/forum/thread/' . $post->getThread()->getId() . '/' . $pageNumber);
}
else {
	error(2);
}

<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$forumHandler = new Forum();
$groupHandler = new Group();
$categoryHandler = new Category();

if (is_numeric($_POST['categoryID'])) {

	/* category exists
	 */
	$category = $categoryHandler->getByID($_POST['categoryID']);

	if (!isset($category['id'])) {
		error(2);
	}

	// forum exists
	if (!$forumHandler->hasForum($category['id'])) {
		SitePage::localRedir('/homepage/' . $category['id']);
	}

	/* check permissions
	 */
	if (($permission = $groupHandler->getPermission($_POST['categoryID'])) < READ_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($_POST['categoryID'])) {
		error(19);
	}

	$post = array(
		'userID' => $_SESSION['id'],
		'body' => $_POST['body'],
	);

	/* modify post
	 */
	if (is_numeric($_POST['postID'])) {
		$current = $forumHandler->getPost($_POST['postID']);

		if (!isset($current['postID'])) {
			error(2);
		}

		/* check user owns post
		 */
		if ($current['userID'] != $_SESSION['id'] && $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
			error(19);
		}

		$thread = $forumHandler->getThread($current['threadID']);

		/* check locked
		 */
		if ($thread['locked'] == 'true' && $permission < WRITE_ACCESS) {
			error(19);
		}

		/* modify thread
		 */
		if ($thread['postID'] == $current['postID']) {
			$data = array(
				'threadID' => $thread['threadID'],
				'title' => $_POST['title'],
			);

			$forumHandler->update($data);
		}

		/* modify post
		 */
		$post['postID'] = $current['postID'];
		$forumHandler->updatePost($post);
	}
	else {
		/* create thread
		 */
		if (!is_numeric($_POST['threadID'])) {
			$thread = array(
				'categoryID' => $_POST['categoryID'],
				'userID' => $_SESSION['id'],
				'title' => $_POST['title'],
			);

			$thread['threadID'] = $forumHandler->create($thread);
		}
		else {
			$thread = $forumHandler->getThread($_POST['threadID']);

			/* thread exists
			 */
			if (!isset($thread['threadID'])) {
				error(2);
			}

			/* check locked
			 */
			if ($thread['locked'] == 'true' && $permission < WRITE_ACCESS) {
				error(19);
			}
		}

		/* create post
		 */
		$post['threadID'] = $thread['threadID'];
		$post['postID'] = $forumHandler->createPost($post);
	}

	SitePage::localRedir('/forum/thread/' . $thread['threadID'] . '/' . $forumHandler->getPageNumber($post['postID']) . '/#' . $post['postID']);
}
else if (is_numeric($_GET['toggleLock'])) {

	/* thread exists
	 */
	$thread = $forumHandler->getThread($_GET['toggleLock']);
	if (!isset($thread['threadID'])) {
		error(2);
	}

	/* forum exists
	 */
	if (!$forumHandler->hasForum($thread['categoryID'])) {
		SitePage::localRedir('/homepage/' . $thread['categoryID']);
	}

	/* check permissions
	 */
	if ($groupHandler->getPermission($thread['categoryID']) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread['categoryID'])) {
		error(19);
	}

	$data = array(
		'threadID' => $thread['threadID'],
		'locked' => $thread['locked'] == 'true' ? 'false' : 'true',
	);

	$forumHandler->update($data);

	$page->backToTheFuture(1);
}
else if (is_numeric($_GET['deleteThread'])) {

	/* thread exists
	 */
	$thread = $forumHandler->getThread($_GET['deleteThread']);
	if (!isset($thread['threadID'])) {
		error(2);
	}

	/* forum exists
	 */
	if (!$forumHandler->hasForum($thread['categoryID'])) {
		SitePage::localRedir('/homepage/' . $thread['categoryID']);
	}

	/* check permissions
	 */
	if ($groupHandler->getPermission($thread['categoryID']) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread['categoryID'])) {
		error(19);
	}

	$forumHandler->delete($thread['threadID']);

	SitePage::localRedir('/forum/' . $thread['categoryID']);
}
else if (is_array($_POST['deleteThreads'])) {
	foreach ($_POST['deleteThreads'] as $threadID) {
		/* thread exists
		 */
		$thread = $forumHandler->getThread($threadID);

		if (isset($thread['threadID'])) {
			/* check permissions
			 */
			if ($groupHandler->getPermission($thread['categoryID']) < WRITE_ACCESS) {
				error(19);
			}
			if ($groupHandler->hasChildAccessOnly($thread['categoryID'])) {
				error(19);
			}

			$forumHandler->delete($thread['threadID']);
		}
	}

	SitePage::localRedir('/forum/' . $thread['categoryID']);
}
else if (is_numeric($_GET['deletePost'])) {

	/* post exists
	 */
	$post = $forumHandler->getPost($_GET['deletePost']);

	if (!isset($post['postID'])) {
		error(2);
	}

	$thread = $forumHandler->getThread($post['threadID']);

	/* check locked
	 */
	if ($thread['locked'] == 'true' && $groupHandler->getPermission($thread['categoryID']) < WRITE_ACCESS) {
		error(19);
	}

	/* check permissions
	 */
	if ($post['userID'] != $_SESSION['id'] && $groupHandler->getPermission($thread['categoryID']) < WRITE_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($thread['categoryID'])) {
		error(19);
	}

	$pageNumber = $forumHandler->getPageNumber($post['postID']);

	$forumHandler->deletePost($post['postID']);

	SitePage::localRedir('/forum/thread/' . $post['threadID'] . '/' . $pageNumber);
}
else {
	error(2);
}

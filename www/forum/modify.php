<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$forumHandler = new Forum();
$categoryHandler = new Category();
$groupHandler = new Group();
$userHandler = new User();

$this->setFile(TEMPLATE_content, 'forum/modify.html');
$this->setBlock(TEMPLATE_content, 'thread_link', 'thread_link_block');
$this->setBlock(TEMPLATE_content, 'title', 'title_block');
$this->setBlock(TEMPLATE_content, 'quote', 'quote_block');

$this->setVar('title_block', '');
$this->setVar('quote_block', '');

if (is_numeric($this->arg[0])) {

	/* Category exists
	 */
	$category = $categoryHandler->getByID($this->arg[0]);

	if (!isset($category['id'])) {
		error(2);
	}

	/* Forum exists
	 */
	if (!$forumHandler->hasForum($category['id'])) {
		SitePage::localRedir('/homepage/' . $category['id']);
	}

	/* Check permissions
	 */
	if ($groupHandler->getPermission($category['id']) < READ_ACCESS) {
		error(19);
	}
	if ($groupHandler->hasChildAccessOnly($category['id'])) {
		error(19);
	}

	$this->setTitle(Scrub::text($category['name'] . ' Forum'));

	/* Edit post
	 */
	if (is_numeric($this->arg[2]) && $this->arg[2] > 0) {
		$post = $forumHandler->getPost($this->arg[2]);

		if (!isset($post['postID'])) {
			error(2);
		}

		/* Check user owns post
		 */
		if ($post['userID'] != $_SESSION['id'] && $groupHandler->getPermission($category['id']) < FULL_ACCESS) {
			error(19);
		}

		$thread = $forumHandler->getThread($post['threadID']);

		/* check locked
		 */
		if ($thread['locked'] == 'true' && $groupHandler->getPermission($category['id']) < FULL_ACCESS) {
			error(19);
		}

		if ($thread['postID'] == $post['postID']) {
			$this->parse('title_block', 'title');
		}

		$this->parse('thread_link_block', 'thread_link');
	}
	/* Add new reply
	 */
	else if (is_numeric($this->arg[1])) {
		$thread = $forumHandler->getThread($this->arg[1]);

		if (!isset($thread['threadID'])) {
			error(2);
		}

		/* Check locked
		 */
		if ($thread['locked'] == 'true' && $groupHandler->getPermission($category['id']) < FULL_ACCESS) {
			error(19);
		}

		$this->parse('thread_link_block', 'thread_link');
	}
	else {
		$this->parse('title_block', 'title');
		$this->setVar('thread_link_block', '');
	}

	$output = array(
		'ACTION' => isset($post['postID']) ? 'Modify' : 'Add',
		'THREAD_POST' => $thread['postID'] == $post['postID'] || !isset($thread['threadID']) ? 'Thread' : 'Post',
		'THREAD_ID' => $thread['threadID'],
		'THREAD_TITLE' => Scrub::text($thread['title']),
		'POST_ID' => $post['postID'],
		'CATEGORY_ID' => $category['id'],
		'CATEGORY' => Scrub::text($category['name']),
		'BODY' => Scrub::html($post['body']),
	);

	$this->setVar($output);

	/* output quote
	 */
	$this->setVar('quote_block', '');

	$quotePostID = $this->arg[3];

	if (!is_numeric($quotePostID)) {
		$quotePostID = $thread['postID'];
	}

	if (is_numeric($quotePostID) && $quotePostID > 0) {
		$quote = $forumHandler->getPost($quotePostID);

		if (isset($quote['postID'])) {
			$user = array(
				'title' => $quote['userTitle'],
				'firstname' => $quote['userFirstname'],
				'lastname' => $quote['userLastname'],
			);

			$output = array(
				'QUOTE_USER' => $userHandler->formatName($user),
				'QUOTE_BODY' => Scrub::html($quote['body']),
			);

			$this->setVar($output);
			$this->parse('quote_block', 'quote', true);
		}
	}
}
else {
	error(2);
}

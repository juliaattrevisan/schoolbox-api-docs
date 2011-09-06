<?php

require_once SITE_root . '/include/SitePage.php';
require_once SITE_root . '/library/category.php';
require_once SITE_root . '/library/forum.php';
require_once SITE_root . '/library/group.php';

$categoryHandler = new Category();
$forumHandler = new Forum();
$groupHandler = new Group();

$this->setFile(TEMPLATE_content, 'forum/forum.html');
$this->setBlock(TEMPLATE_content, 'thread', 'thread_block');
$this->setBlock('thread', 'last_post', 'last_post_block');
$this->setBlock('thread', 'modify_thread', 'modify_thread_block');
$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');

// category exists
if (is_numeric($this->arg[0])) {
	$category = $categoryHandler->getByID($this->arg[0]);
}

if (!isset($category['id'])) {
	error(2);
}

// forum exists
if (!$forumHandler->hasForum($category['id'])) {
	SitePage::localRedir('/homepage/' . $category['id']);
}

// check permissions
if (($perm = $groupHandler->getPermission($category['id'])) < READ_ACCESS) {
	error(19);
}

$this->setTitle(Scrub::text($category['name']) . ' Forum');

/* output breadcrumbs */
$output = array(
	'CATEGORY' => Scrub::text($category['name']),
	'CATEGORY_ID' => $category['id'],
);
$this->setVar($output);

/*Output delete button */
$this->setVar('delete_button_block', '');

if ($perm >= WRITE_ACCESS) {
	$this->parse('delete_button_block', 'delete_button', true);
}

/* output pagination */

$threadList = $forumHandler->getThreadList($category['id']);
$pageNum = $this->arg[1];

if (!$pageNum) {
	$pageNum = 1;
}

$threadPerPage = FORUM_THREADS_PER_PAGE;
$totalPages = ceil(count($threadList) / $threadPerPage);
$threadIndex = $threadPerPage * ($pageNum - 1);

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
			$this->setVar('CURRENT', ' current');
		}
		else
		{
			$this->setVar('CURRENT', '');
		}

		$this->setVar('PAGE_INDEX', $j);
		$this->parse('page_number_block', 'page_number', true);
	}
}
else
{
	$this->setVar('page_number_block', '');
}

$outputPagination = array(
	'PREVIOUS_PAGE' => $pageNum - 1,
	'NEXT_PAGE' => $pageNum + 1,
	'LAST_PAGE' => $totalPages,
);
$this->setVar($outputPagination);

/* output threads */
$threadList = $forumHandler->getThreadList($category['id'], $threadIndex, $threadPerPage);

if (is_array($threadList)) {
	$userHandler = new User();

	foreach ($threadList as $thread) {
		$user = array(
			'title' => $thread['userTitle'],
			'firstname' => $thread['userFirstname'],
			'lastname' => $thread['userLastname'],
		);

		$threadTime = new Alaress_Date_Relative($thread['date']);
		$threadLastPostTime = new Alaress_Date_Relative($thread['last_post_date']);

		$output = array(
			'THREAD_ID' => $thread['threadID'],
			'THREAD_TITLE' => Scrub::text($thread['title']),
			'THREAD_AUTHOR' => Scrub::text($userHandler->formatName($user)),
			'THREAD_TIME' => $threadTime->setPrefix('')->getRelativeTime(),
			'THREAD_LAST_POST_TIME' => $threadLastPostTime->setPrefix('')->getRelativeTime(),
			'THREAD_LAST_POST_ID' => $thread['last_post_id'],
			'THREAD_LAST_POST_USER_ID' => $thread['last_post_user_id'],
			'THREAD_LAST_POST_USER' => Scrub::text($thread['last_post_user']),
			'THREAD_REPLIES' => $thread['replies'],
			'THREAD_VIEWS' => $thread['views'],
			'THREAD_LAST_PAGE' => ceil((($thread['replies'] + 1) / FORUM_POSTS_PER_PAGE)),
			'THREAD_LOCKED' => $thread['locked'] == 'true' ? '(Locked)' : '',
		);

		$this->setVar($output);

		/* Delete checkbox */
		$this->setVar('modify_thread_block', '');

		if ($perm >= WRITE_ACCESS) {
			$this->parse('modify_thread_block', 'modify_thread', true);
		}

		/* last post block */
		$this->setVar('last_post_block', '');

		if ($thread['replies'] > 0) {
			$this->parse('last_post_block', 'last_post', true);
		}

		$this->parse('thread_block', 'thread', true);
	}
}
else {
	$this->setVar('thread_block', '<p>There are no threads to display. Create a new thread.</p>');
}

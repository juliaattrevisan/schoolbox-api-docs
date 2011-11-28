<?php

//dont show forums to parents if they not a direct member of group
if ($groupHandler->isParent() && !$groupHandler->involvedInGroup($_SESSION['id'], $categoryID)) {
	$componentHandle = '';
}
else {
   //forum recent posts

	$this->setBlock($componentHandle, 'forum', 'forum_block');
	$this->setBlock('forum', 'forum_reply', 'forum_reply_block');

	require_once SITE_root . '/library/forum.php';
	$forumHandler = new Forum;

	/* Override default sorting fields with Forum-specific ones
	 */
	$sortOptions = array('Threads', 'Posts');

	$threadList = $forumHandler->getRecentThreadList($categoryID, $config);

	if (is_array($threadList)) {
		$userHandler = new DeprecatedUser();

		foreach ($threadList as $thread) {
			$user = array(
				'title' => $thread['userTitle'],
				'firstname' => $thread['userFirstname'],
				'lastname' => $thread['userLastname'],
			);

			$threadTime = new Alaress_Date_Relative($thread['date']);

			$this->setVar(array(
				'THREAD_ID'			=> $thread['threadID'],
				'THREAD_USER'		=> $userHandler->formatName($user),
				'THREAD_USER_ID'	=> $thread['userID'],
				'THREAD_DATE'		=> $threadTime->getRelativeTime(),
				'THREAD_TITLE'		=> Scrub::text($thread['title']),
				'ON_OFF'			=> ++$j%2? '' : 'dark',
			));

			$this->setVar('forum_reply_block', '');

			if ($thread['locked'] != 'true' || $permission >= FULL_ACCESS) {
				$this->parse('forum_reply_block', 'forum_reply');
			}

			$this->parse("forum_block", "forum", true);
		}
	}
	else {
		$this->setVar("forum_block", '');
	}
}

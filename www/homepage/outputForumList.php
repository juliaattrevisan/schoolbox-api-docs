<?php

/* Tick for checkbox which controls whether to show the Posted By details
 */
if ($config['compactMode']) {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => 'compact-mode',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
	));
}
else {
	$this->setVar(array(
		'COMPONENT_COMPACTMODE' => '',
		'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
	));
}

//dont show forums to parents if they not a direct member of group
if ($groupHandler->isParent() && !$groupHandler->involvedInGroup($_SESSION['id'], $categoryID)) {
	$componentHandle = '';
}
else {
	require_once(SITE_root . '/library/forum.php');

	$this->setBlock($componentHandle, 'forum', 'forum_block');
	$this->setBlock('forum', 'forum_reply', 'forum_reply_block');

	/* Set up component name
	 */
	$this->setVar(array(
		'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Forum',

		/* These are for the Select Alternate Source resourcebrowser
		 */
		'COMPONENT_CONFIG_SRC_ID' => $config['homepage-forum'],
		'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
		'COMPONENT_HANDLE' => strtolower($componentHandle)
	));

	/* Prevent multiple forum entries from other categories stacking up in the wrong place
	 * and initialise empty forum roll by default
	 */
	$this->setVar("forum_block", '');

	/* Override default sorting fields with Forum-specific ones
	 */
	$sortOptions = array('Threads', 'Posts');

	$forumHandler = new Forum();

	/* Select forum feed from alternate source, if specified
	 */
	$alternateCategoryId = false;
	$alternatePermissions = 0;

	/* If you chose an alternate source, check the permissions on it
	 */
	if (strlen($config['homepage-forum'])) {
		$matches = array();
		preg_match('#/homepage/(\d+)#', $config['homepage-forum'], $matches);

		if (count($matches)) {
			$alternatePermissions = $groupHandler->getPermission($matches[1]);

			if ($alternatePermissions >= READ_ACCESS) {
				$alternateCategoryId = $matches[1];
			}
		}
	}

	/* Load news from some other homepage
	 */
	if ($alternateCategoryId) {
		$threadList = $forumHandler->getRecentThreadList($alternateCategoryId, $config);
	}
	/* Tried to load from alternate source but failed
	 */
	else if (strlen($config['homepage-forum']) && !$alternateCategoryId) {
		$threadList = array();
	}
	/* Load forums for the current homepage
	 */
	else if (!strlen($config['homepage-blog'])) {
		$threadList = $forumHandler->getRecentThreadList($categoryID, $config);
	}
	/* Default
	 */
	else {
		$threadList = array();
	}

	/* Iterate
	 */
	if (is_array($threadList)) {
		$userHandler = new User();

		foreach ($threadList as $thread) {
			$user = array(
				'title' => $thread['userTitle'],
				'firstname' => $thread['userFirstname'],
				'lastname' => $thread['userLastname'],
			);

			$threadTime = new Alaress_Date_Relative($thread['date']);

			$this->setVar(array(
				'THREAD_ID' => $thread['threadID'],
				'THREAD_USER' => $userHandler->formatName($user),
				'THREAD_USER_ID' => $thread['userID'],
				'THREAD_DATE' => $threadTime->getRelativeTime(),
				'THREAD_TITLE' => Scrub::text($thread['title']),
				'ON_OFF' => ++$j % 2 ? '' : 'dark',
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

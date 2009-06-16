<?php

require_once SITE_root . '/library/category.php';
require_once SITE_root . '/library/forum.php';
require_once SITE_root . '/library/group.php';

$forumHandler = new Forum;
$categoryHandler = new Category;
$groupHandler = new Group;

$this->setFile(TEMPLATE_content, 'forum/modify.html');
$this->setBlock(TEMPLATE_content, 'title', 'title_block');
$this->setBlock(TEMPLATE_content, 'quote', 'quote_block');

$this->setVar('title_block', '');
$this->setVar('quote_block', '');

/* modify thread */
if ($this->arg[0] == 'thread' && is_numeric($this->arg[1])) {

	// check category exists
	$category = $categoryHandler->getByID($this->arg[1]);
	if (!isset($category['id']))
		error(2);

	$this->setTitle(scrubText($category['name'] . ' Forum'));
	
	// check permissions
	if ($groupHandler->getPermission($category['id']) < READ_ACCESS)
		error(19);

	if (is_numeric($this->arg[2]))
		$thread = $forumHandler->getThread($this->arg[2]);
	
	if (isset($thread['threadID'])) {
		/* check user owns thread */
		if ($thread['userID'] != $_SESSION['id'] && $groupHandler->getPermission($category['id']) < FULL_ACCESS)
			error(19);
	}
	
	$output = array('ACTION'		=> isset($thread['threadID']) ? 'Modify' : 'Add',
					'THREAD_ID'		=> $thread['threadID'],
					'THREAD_TITLE'	=> scrubText($thread['title']),
					'POST_ID'		=> '',
					'CATEGORY_ID'	=> $category['id'],
					'BODY'			=> scrubHTML($thread['body']),
					);
	$this->setVar($output);
	
	$this->parse('title_block', 'title');

} 
/* modify post */
elseif (is_numeric($this->arg[1])) {

	// check thread exists
	$thread = $forumHandler->getThread($this->arg[1]);
	if (!isset($thread['threadID']))
		error(2);

	$category = $categoryHandler->getByID($thread['categoryID']);
	$this->setTitle(scrubText($category['name'] . ' Forum'));

	// check permissions
	if ($groupHandler->getPermission($thread['categoryID']) < READ_ACCESS)
		error(19);

	if (is_numeric($this->arg[2]))
		$post = $forumHandler->getPost($this->arg[2]);
	
	if (isset($post['postID'])) {
		/* check user owns post */
		if ($post['userID'] != $_SESSION['id'] && $groupHandler->getPermission($thread['categoryID']) < FULL_ACCESS)
			error(19);
		
		
	}

	/* output quote */
	$this->setVar('quote_block', '');
	if (is_numeric($this->arg[3])) {
		$quote = $forumHandler->getPost($this->arg[3]);
		if (isset($quote['postID'])) {
	
			$user = array('title'		=> $quote['userTitle'],
						  'firstname'	=> $quote['userFirstname'],
						  'lastname'	=> $quote['userLastname'],
						  );
	
			$output = array('QUOTE_USER'	=> formatName($user),
							'QUOTE_BODY'	=> scrubHTML($quote['body']),
							);
			$this->setVar($output);
			$this->parse('quote_block', 'quote', true);
		}
	}		

	$output = array('ACTION'		=> isset($post['postID']) ? 'Modify' : 'Add',
					'POST_ID'		=> $post['postID'],
					'CATEGORY_ID'	=> $category['id'],
					'THREAD_ID'		=> $thread['threadID'],
					'BODY'			=> scrubHTML($post['body']),
					);
	$this->setVar($output);

	


} else
	error(2);

?>
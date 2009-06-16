<?php

require_once SITE_root . '/library/category.php';
require_once SITE_root . '/library/forum.php';
require_once SITE_root . '/library/group.php';

$categoryHandler = new Category;
$forumHandler = new Forum;
$groupHandler = new Group;

$this->setFile(TEMPLATE_content, 'forum/thread.html');
$this->setBlock(TEMPLATE_content, 'post', 'post_block');

$this->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
$this->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
$this->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');

if (is_numeric($this->arg[0]))
	$thread = $forumHandler->getThread($this->arg[0]);

if (!isset($thread['threadID']))
	error(2);
	
$category = $categoryHandler->getByID($thread['categoryID']);

if ($groupHandler->getPermission($category['id']) < READ_ACCESS)
	error(19);
	
$this->setTitle(scrubText($thread['title']));


/* output breadcrumbs */
$output = array('CATEGORY'		=> scrubText($category['name']),
				'CATEGORY_ID'	=> $category['id'],
				'THREAD_ID'		=> $thread['threadID'],
				'THREAD_TITLE'	=> scrubText($thread['title']),
				
				);
$this->setVar($output);


/* output pagination */

$postList = $forumHandler->getPostList($thread['threadID']);
$pageNum = $this->arg[1];
if(!$pageNum)
	$pageNum = 1;
	
$postPerPage = 9;
$totalPages = ceil(count($postList)/$postPerPage);
$postIndex = $postPerPage * ($pageNum-1);		

$this->setVar('previous_button_block', '');
$this->setVar('next_button_block', '');

if($pageNum >= 2)	
	$this->parse('previous_button_block', 'previous_button');

if($pageNum < $totalPages)
	$this->parse('next_button_block', 'next_button');

if($totalPages > 1) {
	for($j=1; $j <= $totalPages; $j++) {
		if($pageNum == $j)
			 $this->setVar('CURRENT', ' current');
		else
			 $this->setVar('CURRENT', '');
			 
		$this->setVar('PAGE_INDEX', $j);
		$this->parse('page_number_block','page_number', true); 
	}
}
else
	$this->setVar('page_number_block','');

$outputPagination = array('PREVIOUS_PAGE' 	=> $pageNum - 1,
						  'NEXT_PAGE'		=> $pageNum + 1,
						  'LAST_PAGE'		=> $totalPages,
						  );
$this->setVar($outputPagination);	

/* output posts */
$postList = $forumHandler->getPostList($thread['threadID'], $postIndex, $postPerPage);
if (is_array($postList)) {
	foreach ($postList as $post) {
		
		$user = array('title'		=> $post['userTitle'],
					  'firstname'	=> $post['userFirstname'],
					  'lastname'	=> $post['userLastname'],
					  );
					  
		$output = array('POST_ID'			=> $post['postID'],
						'POST_TITLE'		=> scrubText($post['title']),
						'POST_AUTHOR'		=> scrubText(formatName($user)),
						'POST_GROUP'		=> scrubText($post['userGroup']),
						'POST_TIME'			=> getRelativeTime($post['date'], 0, ""),
						'POST_USER_ID'		=> $post['userID'],
						'POST_BODY'			=> $forumHandler->parseBB($post['body']),
						);
		$this->setVar($output);
		
		$this->parse('post_block', 'post', true);
	}
	
} else
	$this->setVar('post_block', '<p>There are no posts to display. Create a new post.</p>');


?>
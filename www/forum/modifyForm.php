<?php

require_once dirname(__FILE__) . '/../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/library/forum.php';
require_once SITE_root . '/library/group.php';

$page = new SitePage;
$forumHandler = new Forum;
$groupHandler = new Group;

/* create/modify post */
if (is_numeric($_POST['threadID']) && is_numeric($_POST['categoryID'])) {
	
	/* check category permissions */
	if ($groupHandler->getPermission($_POST['categoryID']) < READ_ACCESS)
		error(19);

	if (is_numeric($_POST['postID'])) {
		$current = $forumHandler->getPost($_POST['postID']);
		
		/* check user owns post */
		if ($current['userID'] != $_SESSION['userID'] && $groupHandler->getPermission($_POST['categoryID']) < FULL_ACCESS)
			error(19);
	}

	$post = array('threadID'	=> $_POST['threadID'],
				  'userID'		=> $_SESSION['id'],
				  'body'		=> $_POST['body'],
				  );
				  	
	if (is_numeric($_POST['postID'])) {
		$post['postID'] = $_POST['postID'];
		unset($post['threadID']);
		$forumHandler->updatePost($post);
	} else
		$post['postID'] = $forumHandler->createPost($post);
	
	localRedir('/forum/thread/' . $post['threadID'] . '/'.$forumHandler->getPageNumber($post['postID']) .'/#' . $post['postID']);
	

}
/* create/modify thread */
elseif (is_numeric($_POST['categoryID'])) {

	/* check category permissions */
	if ($groupHandler->getPermission($_POST['categoryID']) < READ_ACCESS)
		error(19);
		
	if (is_numeric($_POST['postID'])) {
		$current = $forumHandler->getPost($_POST['postID']);
		
		/* check user owns post */
		if ($current['userID'] != $_SESSION['userID'] && $groupHandler->getPermission($_POST['categoryID']) < FULL_ACCESS)
			error(19);
		
	}
	
	/* thread */
	$thread = array('categoryID'	=> $_POST['categoryID'],
					'userID'		=> $_SESSION['id'],
					'title'			=> $_POST['title'],
					);
					
	if (isset($current['threadID'])) {
		$thread['threadID'] = $current['threadID'];
		
		// ensure thread isn't moved
		unset($thread['categoryID']);
		
		$forumHandler->update($thread);
	} else
		$thread['threadID'] = $forumHandler->create($thread);
		
		
	/* post */
	$post = array('threadID'	=> $thread['threadID'],
				  'userID'		=> $_SESSION['id'],
				  'body'		=> $_POST['body'],
				  );
				  	
	if (isset($current['postID'])) {
		$post['postID'] = $current['postID'];
		// ensure post isn't moved
		unset($post['threadID']);
		$forumHandler->updatePost($post);
	} else
		$post['postID'] = $forumHandler->createPost($post);
	
	
	localRedir('/forum/thread/' . $thread['threadID'] . '/'.$forumHandler->getPageNumber($post['postID']) .'/#' . $post['postID']);
	
}
elseif (is_numeric($_GET['lock'])) {
	$thread = $forumHandler->getThread($_GET['lock']);
	
	if (!isset($thread['threadID']))
		error(2);
	
	/* check category permissions */
	if ($groupHandler->getPermission($thread['categoryID']) < FULL_ACCESS)
		error(19);	

	$data = array('threadID'	=> $thread['threadID'],
				  'locked'		=> 'true',
				  );
	$forumHandler->update($data);		
	
	$page->backToTheFuture(2);

}
elseif (is_numeric($_GET['unlock'])) {
	$thread = $forumHandler->getThread($_GET['unlock']);
	
	if (!isset($thread['threadID']))
		error(2);
	
	/* check category permissions */
	if ($groupHandler->getPermission($thread['categoryID']) < FULL_ACCESS)
		error(19);	

	$data = array('threadID'	=> $thread['threadID'],
				  'locked'		=> 'false',
				  );
	$forumHandler->update($data);		
	
	$page->backToTheFuture(2);
}
elseif (is_numeric($_GET['deleteThread'])) {

	$thread = $forumHandler->getThread($_GET['deleteThread']);
	
	if (!isset($thread['threadID']))
		error(2);

	/* check category permissions */
	if ($groupHandler->getPermission($thread['categoryID']) < FULL_ACCESS)
		error(19);	

	$forumHandler->delete($thread['threadID']);		
	
	localRedir('/forum/' . $thread['categoryID'] );

}


?>
<?php

require_once '../library/Group.php';
require_once '../library/Blog.php';
require_once '../library/Comment.php';

$groupHandle   = new Group();
$blogHandle    = new Blog();
$commentHandle = new Comment();

if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	$blog = $blogHandle->getByID($this->arg[1]);
	
	if($groupHandle->getPermission($blog['cid']) < READ_ACCESS)
		error(19);
	
	if(!empty($blog) && !empty($_POST['body'])) {
		$commentHandle->create(array(
			'bid'  => $blog['id'],
			'body' => $_POST['body']
		));
		
		$this->backToTheFuture(1);
	} else
		error(29);
} elseif($this->arg[0] == 'delete' && is_numeric($this->arg[1])) {
	$comment = $commentHandle->getByID($this->arg[1]);
	if(isset($comment['id']) && $comment['owner'] != $_SESSION['id'] && $groupHandle->getPermission($comment['cid']) < WRITE_ACCESS)
		error(19);
		
	$commentHandle->delete($this->arg[1]);
	
	$this->backToTheFuture(1);
}

?>
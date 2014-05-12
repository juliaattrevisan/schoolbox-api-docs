<?php

$groupHandle   = new Group();

if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
	try {
		/** @var \Schoolbox\Blog\Blog $blog */
		$blog = \Schoolbox\Blog\BlogMapper::getInstance()->findById($this->arg[1]);
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		error(41);
		exit;
	}

	$permission = $groupHandle->getPermission($blog->getCategory()->getId());

	if($permission < READ_ACCESS) {
		error(19);
	}

	if(!empty($blog) && !empty($_POST['body'])) {
		$comment = new \Schoolbox\Blog\Comment();
		$comment
			->setBlog($blog)
			->setCreated(new Alaress_Date_Format(time(), 'U'))
			->setOwner($_SESSION['user'])
			->setBody($_POST['body']);

		\Schoolbox\Blog\CommentMapper::getInstance()->save($comment);
		$this->backToTheFuture(1);
	}
	else {
		error(29);
	}
}
elseif($this->arg[0] == 'delete' && is_numeric($this->arg[1])) {

	try {
		/** @var \Schoolbox\Blog\Comment $comment */
		$comment = \Schoolbox\Blog\CommentMapper::getInstance()->findById($this->arg[1]);
		$comment->setBlog(\Schoolbox\Blog\BlogMapper::getInstance()->findById($comment->getBlog()->getId()));
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		error(19);
		exit;
	}

	$permission = $groupHandle->getPermission($comment->getBlog()->getCategory()->getId());

	if($comment->getOwner()->getId() == $_SESSION['id'] || $permission == FULL_ACCESS) {
		\Schoolbox\Blog\CommentMapper::getInstance()->delete($comment);
		$this->backToTheFuture(1);
	}
	else {
		error(19);
	}

}

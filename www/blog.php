<?php

require_once '../library/group.php';
require_once '../library/blog.php';
require_once '../library/blog-comment.php';

$groupHandler   = new Group();
$blogHandler    = new Blog();
$commentHandler = new Comment();

$this->setFile(TEMPLATE_content, 'blog.html');

if(is_numeric($this->arg[0])) {
	$blog = $blogHandler->getByID($this->arg[0]);
	$permission = $groupHandler->getPermission($blog['cid']);
	
	if(empty($blog))
		error(29);
	
	if($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || NEWS_teacher_edit_other)
		$this->setVar('MODIFY_BLOG', "<a href=\"/blog/modify/{$blog['id']}\">Modify</a> | ");
	else
		$this->setVar('MODIFY_BLOG', '');
	
	$this->setVar(array(
		'BLOG_CID'      => $blog['cid'],
		'BLOG_HEADING'  => $blog['heading'],
		'BLOG_BODY'     => $blog['body'],
		'BLOG_COMMENTS' => 'No Comments',
		'BLOG_ID'       => $blog['id']
	));
	
	$comments = $commentHandler->getByBID($blog['id']);
	$this->setBlock(TEMPLATE_content, 'comment', 'comment_block');
	
	if(!empty($comments)) {
		$this->setVar('BLOG_COMMENTS', count($comments) . ' Comments');
		
		foreach($comments as $comment) {
			$this->setVar(array(
				'COMMENT_BODY'    => $comment['body'],
				'COMMENT_OWNER'   => formatName($comment),
				'COMMENT_CREATED' => getRelativeTime($comment['created']),
				'DELETE_COMMENT'  => "<a href=\"/blog/comment/delete/{$comment['id']}\">Delete</a>"				
			));
			
			if($comment['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || NEWS_teacher_edit_other)
				$this->setVar('DELETE_COMMENT', "<a href=\"/blog/comment/delete/{$comment['id']}\">Delete</a> | ");
			else
				$this->setVar('DELETE_COMMENT', '');
			
			$this->parse('comment_block', 'comment', true);
		}
	} else
		$this->setVar('comment_block', '');
}

?>
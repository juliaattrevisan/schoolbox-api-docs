<?php

require_once '../library/blog.php';
require_once '../library/blog-comment.php';

$blogHandle = new Blog();
$blogHandle->config($config);

$commentHandle = new Comment();
$commentHandle->config($config);

$blogs = $blogHandle->getByCID($categoryID);
$this->setBlock($componentHandle, 'blog', 'blog_block');

if(!empty($blogs)) {
	foreach($blogs as $blog) {
		if($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || NEWS_teacher_edit_other)
			$this->setVar('MODIFY_BLOG', "<a href=\"/blog/modify/{$blog['id']}\">Modify</a>");
		else
			$this->setVar('MODIFY_BLOG', '');
		
		$blog['name'] = formatName($blog);
		$this->setVar(array(
			'BLOG_ID'		=> $blog['id'],
			'BLOG_HEADING'  => scrubTEXT($blog['heading']),
			'BLOG_BODY'     => scrubHTML($blog['body']),
			'BLOG_OWNER'    => "<a href=\"/search/user/{$blog['owner']}\">{$blog['name']}</a>",
			'BLOG_CREATED'  => getRelativeTime($blog['created']),
			'BLOG_SHADING'  => ++$j%2 ? '' : 'dark',
			'BLOG_COMMENTS' => ''
		));
		
		// comments
		$comments = $commentHandle->getByBID($blog['id'], $count);
		$this->setBlock('blog', 'comment', 'comment_block');
		
		if(!empty($comments)) {
			$this->setVar('BLOG_COMMENTS', "<a href=\"/blog/{$blog['id']}\">" . count($comments) . " of {$count['count']} Comments</a>");
		
			foreach($comments as $comment) {
				$comment['name'] = formatName($comment);
				$this->setVar(array(
					'COMMENT_BODY'    => scrubHTML($comment['body']),
					'COMMENT_OWNER'   => "<a href=\"/search/user/{$comment['owner']}\">{$comment['name']}</a>",
					'COMMENT_CREATED' => getRelativeTime($comment['created'])
				));
				
				if($comment['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || NEWS_teacher_edit_other)
					$this->setVar('DELETE_COMMENT', "<a href=\"/blog/comment/delete/{$comment['id']}\">Delete</a>");
				else
					$this->setVar('DELETE_COMMENT', '');
				
				$this->parse('comment_block', 'comment', true);
			}
		} else
			$this->setVar('comment_block', '');
		
		$this->parse('blog_block', 'blog', true);
	}
} else
	$this->setVar('blog_block', '');

$this->setBlock($componentHandle, 'submit', 'submit_block');
if ($permission < WRITE_ACCESS)
	$this->setVar('submit_block', '');
else
	$this->parse('submit_block', 'submit');
	


?>
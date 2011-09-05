<?php

require_once SITE_root.'/library/blog.php';
require_once SITE_root.'/library/blog-comment.php';

$blogHandle = new Blog();
$blogHandle->config($config);

$commentHandle = new Comment();
$commentHandle->config($config);

$this->setBlock('config', 'comments_size', 'comments_size_block');


for($i=0; $i<=10; $i++) {
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_NAME', $i == 0 ? 'All' : $i);
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_VALUE', $i);
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_SELECTED', $i == $config['comments_size'] ? ' selected="selected"' : '');
	$this->parse("comments_size_block", "comments_size", true);
}

$blogs = $blogHandle->getByCID($categoryID);

$this->setBlock($componentHandle, 'blog', 'blog_block');
$this->setBlock('blog', 'comment', 'comment_block');
$this->setBlock('blog', 'show_all', 'show_all_block');
$this->setBlock('blog', 'show_comment', 'show_comment_block');


if(!empty($blogs)) {
	$userHandler = new User();

	foreach($blogs as $blog) {
		if($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $permission >= WRITE_ACCESS))
			$this->setVar('MODIFY_BLOG', "<a href=\"/blog/modify/{$blog['id']}\">Modify</a>");
		else
			$this->setVar('MODIFY_BLOG', '');

		$blog['name'] = $userHandler->formatName($blog);
		$createdTime = new Alaress_Date_Relative($blog['created']);

		$this->setVar(array(
			'BLOG_ID'		=> $blog['id'],
			'BLOG_HEADING'  => Scrub::text($blog['heading']),
			'BLOG_BODY'     => scrubHTML($blog['body']),
			'BLOG_OWNER'    => "<a href=\"/search/user/{$blog['owner']}\">{$blog['name']}</a>",
			'BLOG_CREATED'  => $createdTime->getRelativeTime(),
			'BLOG_SHADING'  => ++$j%2 ? '' : 'dark',
			'BLOG_COMMENTS' => ''
		));

		// comments
		$this->setVar('comment_block','');

		$comments = $commentHandle->getByBID($blog['id']);
		$totalComments = $commentHandle->count($blog['id']);
		$showingComments = count($comments);

		$this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);
		if($totalComments > $showingComments)
			$this->parse('show_all_block','show_all');
		else
			$this->setVar('show_all_block','');

		if(!empty($comments)) {

			$this->setVar('BLOG_COMMENTS', $showingComments);
			$this->parse('show_comment_block','show_comment' );

			$userHandler = new User();

			foreach($comments as $comment) {
				$createdTime = new Alaress_Date_Relative($comment['created']);

				$comment['name'] = $userHandler->formatName($comment);
				$this->setVar(array(
					'COMMENT_BODY'    => Scrub::text($comment['body']),
					'COMMENT_OWNER'   => "<a href=\"/search/user/{$comment['owner']}\">{$comment['name']}</a>",
					'COMMENT_CREATED' => $createdTime->getRelativeTime()
				));

				if($comment['owner'] == $_SESSION['id'] || $permission >= WRITE_ACCESS)
					$this->setVar('DELETE_COMMENT', "<a href=\"/blog/comment/delete/{$comment['id']}\">Delete</a>");
				else
					$this->setVar('DELETE_COMMENT', '');

				$this->parse('comment_block', 'comment', true);
			}
		} else {
			$this->setVar('comment_block', '');
			$this->setVar('show_comment_block', '');
		}
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
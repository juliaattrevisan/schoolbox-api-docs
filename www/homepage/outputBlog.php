<?php
require_once(SITE_root.'/library/blog.php');
require_once(SITE_root.'/library/blog-comment.php');

$this->setBlock($componentHandle, 'blog', 'blog_block');
$this->setBlock($componentHandle, 'submit', 'submit_block');
$this->setBlock('blog', 'comment', 'comment_block');
$this->setBlock('blog', 'show_all', 'show_all_block');
$this->setBlock('blog', 'show_comment', 'show_comment_block');
$this->setBlock('config', 'comments_size', 'comments_size_block');

/* Set up component name
 */
$this->setVar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Blog',

	/* These are for the Select Alternate Source resourcebrowser
	 */
	'COMPONENT_CONFIG_SRC_ID' => $config['homepage-blog'],
	'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
	'COMPONENT_HANDLE' => strtolower($componentHandle)
));

/* Prevent multiple blog entries from other categories stacking up in the wrong place
 * and initialise empty blog roll by default
 */
$this->setVar('blog_block', '');

$blogHandle = new Blog();
$blogHandle->config($config);

$commentHandle = new Comment();
$commentHandle->config($config);

for($i=0; $i<=10; $i++) {
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_NAME', $i == 0 ? 'All' : $i);
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_VALUE', $i);
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_SELECTED', $i == $config['comments_size'] ? ' selected="selected"' : '');
	$this->parse("comments_size_block", "comments_size", true);
}

/* Select news feed from alternate source, if specified
 */
$alternateCategoryId = false;
$alternatePermissions = 0;

/* If you chose an alternate source, check the permissions on it
 */
if (strlen($config['homepage-blog'])) {
	$matches = array();
	preg_match('#/homepage/(\d+)#', $config['homepage-blog'], $matches);

	if (count($matches)) {
		$alternatePermissions = $groupHandler->getPermission($matches[1]);

		if ($alternatePermissions >= READ_ACCESS) {
			$alternateCategoryId = $matches[1];
		}
	}
}

/* Load blog from some other homepage
 */
if ($alternateCategoryId) {
	$blogs = $blogHandle->getByCID($alternateCategoryId);

	/* Controls such as Post News
	 */
	if ($alternatePermissions < WRITE_ACCESS) {
		$this->setVar("submit_block", "");
	}
	else {
		$this->setVar(array(
			'BLOG_CATEGORY_ID' => $alternateCategoryId
		));

		$this->parse("submit_block", "submit");
	}
}
/* Tried to load from alternate source but failed
 */
else if (strlen($config['homepage-blog']) && !$alternateCategoryId) {
	$blogs = array();

	/* Have update form to back to this homepage, not the wiki's homepage
	 */
	$this->setVar(array(
		'BLOG_CATEGORY_ID' => $categoryID
	));

	$this->setVar("submit_block", "");
}
/* Load blogs for the current homepage
 */
else if (!strlen($config['homepage-blog'])){
	$blogs = $blogHandle->getByCID($categoryID);

	/* Have update form to back to this homepage, not the wiki's homepage
	 */
	$this->setVar(array(
		'BLOG_CATEGORY_ID' => $categoryID
	));

	/* Controls such as Post News
	 */
	if ($permission < WRITE_ACCESS) {
		$this->setVar("submit_block", "");
	}
	else {
		$this->parse("submit_block", "submit");
	}
}
/* Default
 */
else {
	$blogs = array();

	$this->setVar("submit_block", "");
}

/* Iterate
 */
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
			'BLOG_BODY'     => Scrub::html($blog['body']),
			'BLOG_OWNER'    => "<a href=\"/search/user/{$blog['owner']}\">{$blog['name']}</a>",
			'BLOG_CREATED'  => $createdTime->getRelativeTime(),
			'BLOG_SHADING'  => ++$j%2 ? '' : 'dark',
			'BLOG_COMMENTS' => '',
			'BLOG_AUTHOR_EXTERNAL_ID' => $blog['synergy_id'],
			'BLOG_AUTHOR_NAME' => $blog['firstname'] . ' ' . $blog['lastname']
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
					'COMMENT_CREATED' => $createdTime->getRelativeTime(),
					'COMMENT_AUTHOR_EXTERNAL_ID' => $comment['synergy_id'],
					'COMMENT_AUTHOR_NAME' => $comment['firstname'] . ' ' . $comment['lastname']
				));

				if($comment['owner'] == $_SESSION['id'] || $permission >= WRITE_ACCESS)
					$this->setVar('DELETE_COMMENT', "<a class=\"delete-comment\" href=\"/blog/comment/delete/{$comment['id']}\">Delete</a>");
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
}
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/blog.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/blog-comment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';

$groupHandler = new Group;
$blogHandler = new Blog;
$commentHandle = new Comment();
$categoryHandler = new Category;

$this->setFile(TEMPLATE_content, "homepage/blogArchive.html");
$this->setBlock(TEMPLATE_content, "submit_blog", "submit_blog_block");

$this->setBlock(TEMPLATE_content, 'blog', 'blog_block');
$this->setBlock('blog', 'comment', 'comment_block');
$this->setBlock('blog', 'show_all', 'show_all_block');
$this->setBlock('blog', 'show_comment', 'show_comment_block');

$this->setBlock(TEMPLATE_content, "year", "year_block");
$this->setBlock('year', 'month', 'month_block');

/* Check for valid arguments */
if ( !($category = $categoryHandler->getById($this->arg[0])) ) {
    $this->error(2);
}

if (!($blog = $blogHandler->getByCID($this->arg[0]))) {
	$this->error(111);
}

/* Set default year and month if none passed */
$yearNumber = $this->arg[1];
$monthNumber = $this->arg[2];

if ( empty($yearNumber) ) {
	$yearNumber = date('Y');
}

if ( empty($monthNumber) ) {
	$monthNumber = date('m');
}

$this->setVar(array(
    'CATEGORY_NAME'	=> Scrub::text($category['name']),
    'TITLE'			=> 'Blog Archive',
    'ID'			=> $category['id'],
));

/* Hide 'Add' button if not authorised */
if ($groupHandler->getPermission($category['id']) >= WRITE_ACCESS) {
	$this->parse('submit_blog_block', 'submit_blog');
}
else {
	$this->setVar('submit_blog_block', '');
}

/* Year and Month List */
if ( $dateList = $blogHandler->getOccupiedDateList($category['id']) ) {
	foreach ($dateList as $i => $date) {
		$this->setVar( array(
			'YEAR' => $date['year'],
			'YEAR_ClASS' => $date['year'] == $yearNumber ? 'selected' : ''
		) );

		$month = new Alaress_Date_Format($date['created'], 'y-m-d');

		$this->setVar( array(
		   'MONTH' => $month->getUsingFormat('F'),
		   'MONTH_NUMBER' => $month->getUsingFormat('m'),
		   'MONTH_ClASS' => $month->getUsingFormat('m') == $monthNumber ? 'selected' : ''
		) );

		$this->parse('month_block', 'month', true);

		$nextYear = $dateList[$i+1]['year'];

		if ( $date['year'] != $nextYear || empty($nextYear) ) {
			$this->parse('year_block', 'year', true);
			$this->setVar('month_block', '');
		}
	}
}

/* Blog Posts */
if ( $blogList = $blogHandler->getByYearAndMonth($category['id'], $yearNumber, $monthNumber) ) {
	foreach ($blogList as $blog) {
		$currentDate = date_parse($blog['created']);

		/* Modify Action */
		if ($blog['owner'] == $_SESSION['id'] || $groupHandler->getPermission($blog['id']) >= WRITE_ACCESS) {
			$this->setVar("MOD_BLOG", "<a href=\"/blog/modify/".$blog['id']."\">Modify</a>");
		}
		else {
			$this->setVar("MOD_BLOG", "");
		}

		/* Post */
		$userHandler = new User();

		$poster = $userHandler->formatName($blog);

		$postedDate = new Alaress_Date_Format($blog['createdTimestamp'], 'U');

		$this->setVar(array(
			'BLOG_ID' => $blog['id'],
			'BLOG_HEADING' => $blog['heading'],
			'BLOG_BODY' => $blog['body'],
			'EMAIL_CONTACT' => "<a href=\"/search/user/{$blog['uid']}\">$poster</a>",
			'POSTED_DATE' => $postedDate->getShort(),
			'BLOG_COMMENTS' => ''
		));

		/* Comments */
		$this->setVar('comment_block','');

		$comments = $commentHandle->getByBID($blog['id']);
		$totalComments = $commentHandle->count($blog['id']);
		$showingComments = count($comments);

		$this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);

		if($totalComments > $showingComments) {
			$this->parse('show_all_block','show_all');
		}
		else {
			$this->setVar('show_all_block','');
		}

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

				if ($comment['owner'] == $_SESSION['id'] || $permission >= WRITE_ACCESS) {
					$this->setVar('DELETE_COMMENT', "<a class=\"delete-comment\" href=\"/blog/comment/delete/{$comment['id']}\">Delete</a>");
				}
				else {
					$this->setVar('DELETE_COMMENT', '');
				}

				$this->parse('comment_block', 'comment', true);
			}
		} else {
			$this->setVar('comment_block', '');
			$this->setVar('show_comment_block', '');
		}

		$this->parse('blog_block', 'blog', true);
	}
}

?>

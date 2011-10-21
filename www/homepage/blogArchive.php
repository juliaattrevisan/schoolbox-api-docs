<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/blog.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';

$groupHandler = new Group;
$blogHandler = new Blog;
$categoryHandler = new Category;

$this->setFile(TEMPLATE_content, "homepage/blogArchive.html");
$this->setBlock(TEMPLATE_content, "submit_blog", "submit_blog_block");
$this->setBlock(TEMPLATE_content, 'blog', 'blog_block');
$this->setBlock(TEMPLATE_content, "year", "year_block");
$this->setBlock('year', 'month', 'month_block');

if ( !($category = $categoryHandler->getById($this->arg[0])) ) {
    $this->error(2);
}

if (!($blog = $blogHandler->getByCID($this->arg[0]))) {
	$this->error(111);
}

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

/* Hide 'Add' button if not permissible
 */
if ($groupHandler->getPermission($category['id']) >= WRITE_ACCESS) {
	$this->parse('submit_blog_block', 'submit_blog');
}
else {
	$this->setVar('submit_blog_block', '');
}

$yearList = $blogHandler->postYears($category['id']);

foreach ($yearList as $key => $year) {
    $this->setVar( array(
		'YEAR' => $year['year'],
		'YEAR_ClASS' => $year['year'] == $yearNumber ? 'selected' : ''
	) );

	$monthList = $blogHandler->postMonths($category['id'], $year['year']);

	foreach ( $monthList as $month ) {
		$monthFormat = new Alaress_Date_Format($month['month'], 'y-m-d');

		$this->setVar( array(
		   'MONTH' => $monthFormat->getUsingFormat('F'),
		   'MONTH_NUMBER' => $monthFormat->getUsingFormat('m'),
		   'MONTH_ClASS' => $monthFormat->getUsingFormat('m') == $monthNumber ? 'selected' : ''
		) );

		$this->parse('month_block', 'month', true);
	}

    $this->parse('year_block', 'year', true);
	$this->setVar('month_block', '');
}

$blogList = $blogHandler->getByYearAndMonth($category['id'], $yearNumber, $monthNumber);

foreach ($blogList as $blog) {
	$currentDate = date_parse($blog['created']);

	/* Prepare modify links
	 */
	if ($blog['owner'] == $_SESSION['id'] || $groupHandler->getPermission($blog['id']) >= WRITE_ACCESS) {
		$this->setVar("MOD_BLOG", "<a href=\"/blog/modify/".$blog['id']."\">Modify</a>");
	}
	else {
		$this->setVar("MOD_BLOG", "");
	}

	/* Put blog contents in
	 */
	$userHandler = new User();

	$poster = $userHandler->formatName($blog);

	$postedDate = new Alaress_Date_Format($blog['createdTimestamp'], 'U');

	$this->setVar(array(
		'BLOG_ID' => $blog['id'],
		'BLOG_HEADING' => $blog['heading'],
		'BLOG_BODY' => $blog['body'],
		'EMAIL_CONTACT' => "<a href=\"/search/user/{$blog['uid']}\">$poster</a>",
		'POSTED_DATE' => $postedDate->getShort()
	));

	$this->parse('blog_block', 'blog', true);
}

?>

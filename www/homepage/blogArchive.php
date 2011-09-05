<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/blog.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';

$groupHandler = new Group;
$blogHandler = new Blog;
$categoryHandler = new Category;

$this->setFile(TEMPLATE_content, "homepage/blogArchive.html");
$this->setBlock(TEMPLATE_content, "submit_blog", "submit_blog_block");
$this->setBlock(TEMPLATE_content, "year", "year_block");
$this->setBlock('year', 'month', 'month_block');
$this->setBlock('month', 'monthLink', 'monthLink_block');
$this->setBlock('month', 'blog', 'blog_block');

if (!($category = $categoryHandler->getById($this->arg[0]))) {
    $this->error(2);
}

if (!($blog = $blogHandler->getByCID($this->arg[0]))) {
	$this->error(111);
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

$blogList = $blogHandler->getByCID($category['id']);
$blogYears = $blogHandler->postYears($category['id']);

foreach ($blogYears as $key => $current) {
    $blogList = $blogHandler->getByCIDAndYear($category['id'], $current['year']);
    $prevMonth = 12;
    $i = 0;

    $this->setVar(array(
        'YEAR' => $current['year']
    ));

    foreach ($blogList as $blog) {
        $currentDate = date_parse($blog['created']);

        /* Full-text month link
         */
        $this->setVar(array(
            'MONTH' => date('F', $blog['createdTimestamp'])
        ));

        /* If the current month starts off the same as prevMonth, skip this,
         * otherwise you won't have links for that month.
         */
        if (($currentDate['month'] == $prevMonth) && $i) {
            $this->setVar('monthLink_block', '');
        }
        else {
            $this->parse('monthLink_block', 'monthLink');
        }

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
        $this->parse('month_block', 'month', true);
        $this->setVar('blog_block', '');

        $prevMonth = (int)$currentDate['month'];
        $i++;
    }

    $this->parse('year_block', 'year', true);
    $this->setVar('month_block', '');
}

?>

<?php

$groupHandler = new Group;
$blogHandler = new Blog;
$commentHandle = new Comment();
$categoryHandler = new Category;

$this->setFile(TEMPLATE_content, "homepage/blogArchive.html");
$this->setBlock(TEMPLATE_content, "submit_blog", "submit_blog_block");

$this->setBlock(TEMPLATE_content, 'blog', 'blog_block');
$this->setBlock('blog', 'comment', 'comment_block');

$this->setBlock(TEMPLATE_content, "year", "year_block");
$this->setBlock('year', 'month', 'month_block');

/* Check for valid arguments */
if (!($category = $categoryHandler->getById($this->arg[0]))) {
    $this->error(2);
}

if (!($blog = $blogHandler->getByCID($this->arg[0]))) {
    $this->error(111);
}

/* Set default year and month if none passed */
$yearNumber = $this->arg[1];
$monthNumber = $this->arg[2];

if (empty($yearNumber)) {
    $yearNumber = date('Y');
}

if (empty($monthNumber)) {
    $monthNumber = date('m');
}

$this->setVar(
    array(
        'CATEGORY_NAME' => Scrub::text($category['name']),
        'TITLE' => 'Blog Archive',
        'ID' => $category['id'],
        'blog_block' => '',
    )
);

/* Hide 'Add' button if not authorised */
if ($groupHandler->getPermission($category['id']) >= WRITE_ACCESS) {
    $this->parse('submit_blog_block', 'submit_blog');
} else {
    $this->setVar('submit_blog_block', '');
}

/* Year and Month List */
if ($dateList = $blogHandler->getOccupiedDateList($category['id'])) {
    foreach ($dateList as $i => $date) {
        $this->setVar(
            array(
                'YEAR' => $date['year'],
                'YEAR_ClASS' => $date['year'] == $yearNumber ? 'selected' : ''
            )
        );

        $month = new Alaress_Date_Format($date['created'], 'y-m-d');

        /* Highlight only for the selected month and selected year
         */
        if (($month->getUsingFormat('m') == $monthNumber) && ($month->getUsingFormat('Y') == $yearNumber)) {
            $monthClass = 'selected';
        } else {
            $monthClass = '';
        }

        $this->setVar(
            array(
                'MONTH' => $month->getUsingFormat('F'),
                'MONTH_NUMBER' => $month->getUsingFormat('m'),
                'MONTH_ClASS' => $monthClass,
            )
        );

        $this->parse('month_block', 'month', true);

        $nextYear = $dateList[$i + 1]['year'];

        if ($date['year'] != $nextYear || empty($nextYear)) {
            $this->parse('year_block', 'year', true);
            $this->setVar('month_block', '');
        }
    }
}

/* Blog Posts */
if ($blogList = $blogHandler->getByYearAndMonth($category['id'], $yearNumber, $monthNumber)) {

    $i = 0;
    foreach ($blogList as $blog) {
        $currentDate = date_parse($blog['created']);

        /* Modify Action */
        if ($blog['owner'] == $_SESSION['id'] || $groupHandler->getPermission($blog['id']) >= WRITE_ACCESS) {
            $this->setVar(
                "MOD_BLOG", '<a class="btn modify" href="/blog/modify/' . $blog['id'] . '"><span></span>Modify</a>'
            );
        } else {
            $this->setVar("MOD_BLOG", '');
        }

        /* Post */
        $userHandler = new User();

        $user = $userHandler->getByID($blog['owner']);

        $poster = $userHandler->formatName($user);
        $synID = $user['synergy_id'];

        $postedDate = new Alaress_Date_Format($blog['createdTimestamp'], 'U');

        $this->setVar(
            array(
                'BLOG_ID' => $blog['id'],
                'BLOG_HEADING' => Scrub::text($blog['heading']),
                'BLOG_BODY' => Scrub::html($blog['body']),
                'BLOG_AUTHOR_ID' => $blog['owner'],
                'BLOG_AUTHOR_NAME' => $poster,
                'BLOG_AUTHOR_THUMB' => "/portrait.php?userId=$synID&size=square32",
                'BLOG_AUTHOR_GROUP' => $postedDate->getShort(),
                'BLOG_COMMENTS' => '',
                'BLOG_SHADING' => ++$i % 2 ? 'dark' : ''
            )
        );

        /* Comments */
        $this->setVar('comment_block', '');

        $comments = $commentHandle->getByBID($blog['id']);
        $totalComments = $commentHandle->count($blog['id']);
        $showingComments = count($comments);

        $this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);

        if (!empty($comments)) {
            $j = 0;
            $userHandler = new User();

            foreach ($comments as $comment) {
                $createdTime = new Alaress_Date_Relative($comment['created']);

                $comment['name'] = $userHandler->formatName($comment);

                $this->setVar(
                    array(
                        'COMMENT_BODY' => Scrub::text($comment['body']),
                        'COMMENT_OWNER' => "<a href=\"/search/user/{$comment['owner']}\">{$comment['name']}</a>",
                        'COMMENT_CREATED' => $createdTime->getRelativeTime(),
                        'COMMENT_AUTHOR_ID' => $comment['owner'],
                        'COMMENT_AUTHOR_NAME' => $comment['name'],
                        'COMMENT_AUTHOR_EXTERNAL_ID' => $comment['synergy_id'],
                        'ON_OFF' => ++$j % 2 ? 'dark' : ''
                    )
                );

                if ($comment['owner'] == $_SESSION['id'] || $permission >= WRITE_ACCESS) {
                    $this->setVar(
                        'DELETE_COMMENT',
                        "<a class=\"delete-comment btn delete no-text\" href=\"/blog/comment/delete/{$comment['id']}\"><span></span></a>"
                    );
                } else {
                    $this->setVar('DELETE_COMMENT', '');
                }

                $this->parse('comment_block', 'comment', true);
            }
        } else {
            $this->setVar('comment_block', '');
        }

        $this->parse('blog_block', 'blog', true);
    }
}

?>

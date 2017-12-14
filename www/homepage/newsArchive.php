<?php

$groupHandler = new Group;
$newsHandler = new CategoryNews;
$userHandler = new User();

$categoryMapper = new \Schoolbox\Resource\Folder\Mapper();

$this->setFile(TEMPLATE_content, "homepage/newsArchive.html");
$this->setBlock(TEMPLATE_content, "submit_news", "submit_news_block");
$this->setBlock(TEMPLATE_content, "news", "news_block");
$this->setBlock('news', "can_modify", "can_modify_block");

$this->setVar(array(
    'can_modify_block' => ''
));

try {
    $category = $categoryMapper->findById($this->arg[0]);
} catch (\Exception $e) {
    $this->error(2);
}

$output = array('CATEGORY_NAME'	=> Scrub::text($category->getName()),
    'TITLE'			=> 'News Archive',
    'ID'			=> $category->getId(),
);
$this->setVar($output);

//hide add button
if ($groupHandler->getPermission($category->getId()) >= WRITE_ACCESS) {
    $this->parse("submit_news_block", "submit_news");
} else {
    $this->setVar("submit_news_block", "");
}


$newsList = $newsHandler->getAllForCategory($category->getId(), false, true);

if (is_array($newsList)) {
    $j = 0;

    $userHandler = new User();

    foreach ($newsList as $news) {
        $poster = $userHandler->formatName($news);

        $postedDate = new Alaress_Date_Format($news['published'], 'U');

        $output = array(
            "NEWS_ID"           => $news['id'],
            "NEWS_HEADING"	=> Scrub::text($news['heading']),
            "NEWS_BODY"		=> Scrub::html($news['body']),
            "EMAIL_CONTACT"	=> "<a href=\"/search/user/{$news['uid']}\">$poster</a>",
            "POSTED_DATE"	=> $postedDate->getShort(),

            "AUTHOR" => $userHandler->formatName($news),
            "AUTHOR_ID" => $news['uid'],
            "AUTHOR_EXTERNAL_ID" => $news['synid'],
            'ON_OFF' => ++$j % 2 ? '' : 'dark'
        );
        $this->setVar($output);

        if ($article['owner'] == $_SESSION['id'] || $groupHandler->getPermission($category->getId()) >= WRITE_ACCESS) {
            $this->parse('can_modify_block', 'can_modify');
        } else {
            $this->setVar(array(
                'can_modify_block' => ''
            ));
        }

        $this->parse("news_block", "news", true);

        /* Clear children after parsing out the parent
         */
        $this->setVar(array(
            'can_modify_block' => ''
        ));
    }
} else {
    $this->setVar("news_block", "");
}

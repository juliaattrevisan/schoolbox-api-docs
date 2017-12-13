<?php

use Schoolbox\Resource\Category\NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Error\Mapper as ErrorMapper;

$groupHandler = new Group;
$newsHandler = new CategoryNews;
$userHandler = new User();

$categoryMapper = new FolderMapper();

$this->setFile(TEMPLATE_content, "homepage/news.html");
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

if($groupHandler->getPermission($category->getId()) < READ_ACCESS) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
}

$output = array('CATEGORY_NAME'	=> Scrub::text($category->getName()),
    'TITLE'			=> 'News',
    'ID'			=> $category->getId(),
);
$this->setVar($output);

//hide add button
if ($groupHandler->getPermission($category->getId()) >= WRITE_ACCESS) {
    $this->parse("submit_news_block", "submit_news");
} else {
    $this->setVar("submit_news_block", "");
}

$subjectNewsMapper = new NewsMapper();

try {
    $news = $subjectNewsMapper->findById($this->arg[1]);
} catch (\Schoolbox\Mapper\Exception\Deleted $e) {
    error(ErrorMapper::ERROR_POST_NOT_FOUND);
}

if ($news->getOwner() == $_SESSION['id'] || $groupHandler->getPermission($category->getId()) >= WRITE_ACCESS) {
    $this->parse('can_modify_block', 'can_modify');
} else {
    $this->setVar(array(
        'can_modify_block' => ''
    ));
}

$output = array(
    "NEWS_ID" => $news->getId(),
    "NEWS_HEADING" => Scrub::text($news->getHeading()),
    "NEWS_BODY" => Scrub::html($news->getBody()),
    "EMAIL_CONTACT" => "", //"<a href=\"/search/user/{$news['uid']}\">$poster</a>",
    "PUBLISH_DATE" => $news->getPublishDate()->getShort(),
    "AUTHOR" => $news->getOwner()->formatName(),
    "AUTHOR_ID" => $news->getOwner()->getId(),
    "AUTHOR_EXTERNAL_ID" => $news->getOwner()->getExternalId()
);

$this->setVar($output);

$this->parse("news_block", "news", true);

/* Clear children after parsing out the parent
 */
$this->setVar(array(
    'can_modify_block' => ''
));

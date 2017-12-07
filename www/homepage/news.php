<?php

use Schoolbox\Resource\Category\NewsMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

$groupHandler = new Group;
$newsHandler = new CategoryNews;
$userHandler = new User();

$categoryMapper = new FolderMapper();

$this->setFile(TEMPLATE_content, "homepage/news.html");
$this->setBlock(TEMPLATE_content, "submit_news", "submit_news_block");
$this->setBlock(TEMPLATE_content, "news", "news_block");
$this->setBlock('news', "can_modify", "can_modify_block");
$this->setBlock('news', 'poster_link_open', 'poster_link_open_block');
$this->setBlock('news', 'poster_link_close', 'poster_link_close_block');

$this->setVar(array(
    'can_modify_block' => ''
));

try {
    $category = $categoryMapper->findById($this->arg[0]);
} catch (\Exception $e) {
    $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
}

if($groupHandler->getPermission($category->getId()) < READ_ACCESS) {
    $this->error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
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
    $this->error(ErrorMapper::ERROR_POST_NOT_FOUND);
}

if ($news->getOwner() == $_SESSION['id'] || $groupHandler->getPermission($category->getId()) >= WRITE_ACCESS) {
    $this->parse('can_modify_block', 'can_modify');
} else {
    $this->setVar(array(
        'can_modify_block' => ''
    ));
}

// phplib weirdness - gets overridden if user is found to exist
$this->setVar('poster_link_open_block', '');
$this->setVar('poster_link_close_block', '');

$userId = $news->getOwner()->getId();
if (isset($userId)) {
    $deletedAt = $news->getOwner()->getDeletedAt();
    if ($deletedAt instanceof \Alaress_Date_NullDate) {
        // render a user profile link only if user hasn't been soft-deleted
        $this->parse('poster_link_open_block', 'poster_link_open');
        $this->parse('poster_link_close_block', 'poster_link_close');
    }
}

$output = array(
    "NEWS_ID"       => $news->getId(),
    "NEWS_HEADING"	=> Scrub::text($news->getHeading()),
    "NEWS_BODY"		=> Scrub::html($news->getBody()),
    "EMAIL_CONTACT"	=> "", //"<a href=\"/search/user/{$news['uid']}\">$poster</a>",
    "PUBLISH_DATE"	=> $news->getPublishDate()->getShort(),
    "AUTHOR"        => $news->getOwner()->formatName(),
    "AUTHOR_ID"     => $news->getOwner()->getId(),
    "AUTHOR_EXTERNAL_ID" => $news->getOwner()->getExternalId()
);

$this->setVar($output);

$this->parse("news_block", "news", true);

/* Clear children after parsing out the parent
 */
$this->setVar(array(
    'can_modify_block' => ''
));

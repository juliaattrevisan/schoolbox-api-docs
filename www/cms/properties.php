<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page         = new SitePage;
$fileMapper   = Schoolbox\Resource\File\Mapper::getInstance();
$groupHandler = new Group;

$page->setFile(TEMPLATE_body, 'cms/properties.html');
$page->setBlock(TEMPLATE_body, 'realname', 'realname_block');
$page->setBlock(TEMPLATE_body, 'link', 'link_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');

if (is_numeric($_GET['fileID'])) {
    $file = $fileMapper->findById($_GET['fileID']);

    $folder = $file->getFolder();
    $folderId = $folder->getId();

    if ($groupHandler->getPermission($folderId) < READ_ACCESS) {
        error(19);
    }

    if ($groupHandler->getPermission($folderId) < WRITE_ACCESS && $file->isHidden() > 0) {
        error(19);
    }

    $output = array(
        'FILE_ID'     => $file->getId(),
        'CATEGORY'    => $folder->getName(),
        'OWNER'       => $file->getOwner()->formatName(),
        'OWNER_ID'    => $file->getOwner()->getId(),
        'REALNAME'    => $file->getBasename(),
        'CREATED'     => $file->getCreatedAt()->getLocalShort(),
        'DESCRIPTION' => Scrub::text($file->getTitle()),
        'COMMENT'     => Scrub::text($file->getDescription()),
        'SIZE'        => Alaress_Byte_Format::bytesToString($file->getSize()),
        'LINK'        => '/send.php?id=' . $file->getId(),
        'HOST'        => SitePage::conType() . $_SERVER['HTTP_HOST'],
        'MIME_TYPE'   => $file->getMime(),
        'HIDDEN'      => $file->isHidden() ? 'Yes' : 'No',
    );
    $page->setVar($output);
    $page->parse('realname_block', 'realname');
    $page->setVar('link_block', '');
    $page->parse('file_block', 'file');


    $page->outputPage(true);

} elseif (is_numeric($_GET['linkID'])) {
    try {
        $linkMapper = new \Schoolbox\Resource\LinkMapper();
        $link       = $linkMapper->findById($_GET['linkID']);
    } catch (\Exception $e) {
            error(101);
    }

    if ($groupHandler->getPermission($link->getFolder()->getId()) < READ_ACCESS) {
        error(19);
    }

    if ($groupHandler->getPermission($link->getFolder()->getId()) < WRITE_ACCESS && $link->getHidden() > 0) {
        error(19);
    }

    /* All this can be avoided by hydrating first-tier objects into findById
     * by default, instead of trying to be ultra-compact
     */
    try {
        $categoryMapper = new \Schoolbox\Resource\CategoryMapper();
        $category       = $categoryMapper->findById($link->getFolder()->getId());
    } catch (\Exception $e) {
        error(13);
    }

    try {
        $userMapper = new \Schoolbox\UserMapper();
        $user       = $userMapper->findById($link->getOwner()->getId());
    } catch (\Exception $e) {
        error(31);
    }

    $output = array(
        'FILE_ID'     => $link->getId(),
        'CATEGORY'    => $category->getName(),
        'OWNER'       => $user->formatName(),
        'OWNER_ID'    => $user->getId(),
        'URL'         => $link->getUrl(),
        'CREATED'     => $link->getSubmitted()->getShort(),
        'DESCRIPTION' => Scrub::text($link->getDescription()),
        'COMMENT'     => Scrub::text($link->getComment()),
        'LINK'        => $link->getUrl(),
        'HOST'        => '',
        'HIDDEN'      => $link->getHidden() > 0 ? 'Yes' : 'No',
    );

    $page->setVar($output);
    $page->setVar('realname_block', '');
    $page->setVar('file_block', '');
    $page->parse('link_block', 'link');

    $page->outputPage(true);

} else {
    error(2);
}

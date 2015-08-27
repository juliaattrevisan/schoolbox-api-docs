<?php

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Blog\BlogMapper;
use Schoolbox\Breadcrumbs;

$groupHandle = new Group();
$blogHandle = new Blog();

$this->setFile(TEMPLATE_content, 'blog/modify.html');

if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
    if ($groupHandle->getPermission($this->arg[1]) < WRITE_ACCESS) {
        error(23);
    }

    try {
        $folder = FolderMapper::getInstance()->findById($this->arg[1]);
    } catch (Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    $this->setTitle('Post Blog Entry');

    $this->setBlock(TEMPLATE_content, 'delete', 'delete_block');
    $this->setVar(
        array(
            'ACTION'        => 'Post',
            'ENTRY_HEADING' => '',
            'ENTRY_BODY'    => '',
            'CID'           => $this->arg[1],
            'CATEGORY_NAME' => Scrub::text($folder->getName()),
            'ID'            => '',
            'delete_block'  => ''
        )
    );

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => Scrub::text($folder->getName()),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Post New Blog',
            'href' => "#",
            'class' => 'active',
        ]
    ]);

} elseif (is_numeric($this->arg[0])) {

    try {
        $blog = BlogMapper::getInstance()->findById($this->arg[0]);
        /* TODO hydrate properly */
        $blog->setCategory(FolderMapper::getInstance()->findById($blog->getCategory()->getId()));
    } catch (Schoolbox\Mapper\Exception $e) {
        error(2);
    }

    $this->setTitle('Modify Blog Entry');
    if (!$blogHandle->permission($blog->getId())) {
        error(23);
    }

    $this->setVar(
        array(
            'ACTION'        => 'Modify',
            'ENTRY_HEADING' => Scrub::text($blog->getHeading()),
            'ENTRY_BODY'    => Scrub::text($blog->getBody()),
            'CATEGORY_NAME' => Scrub::text($blog->getCategory()->getName()),
            'CID'           => $blog->getCategory()->getId(),
            'ID'            => $blog->getId(),
        )
    );

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => Scrub::text($blog->getCategory()->getName()),
            'href' => "/homepage/{$blog->getCategory()->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Modify Blog',
            'href' => "#",
            'class' => 'active',
        ]
    ]);
}


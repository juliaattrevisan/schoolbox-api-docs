<?php

use Schoolbox\Breadcrumbs;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\My\Folder\Model as MyFolderModel;

$userMapper = new Schoolbox\UserMapper();
$folderMapper = Schoolbox\Resource\My\Folder\Mapper::getInstance();

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

/** @var SitePage $this */
if (is_numeric($this->arg[0]) && $this->arg[0] > 0) {
    try {
        /** @var Schoolbox\Resource\My\Folder\Model $folder */
        $folder = $folderMapper->findOneByColumnList([
            'id'    => $this->arg[0],
            'owner' => $sessionUser->getId(),
        ]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
    }

    $this->setFile(TEMPLATE_content, "cms/myFolder.html");
    $this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


    $this->setTitle('Modify Folder');
    $this->setTab('myfiles');

    $output = array(
        'ACTION' => 'Modify',
        'FOLDER_ID' => $folder->getId(),
        'NAME' => Scrub::text($folder->getName()),
        'PARENT_ID' => $folder->getParent()->getId(),
    );
    $this->setVar($output);

    if (Config::get('webdav_enable')) {
        $this->setBlock(TEMPLATE_content, 'location', 'location_block');
        $this->setVar('location_block', '');
    } else {
        $folderTree = $folderMapper->loadDescendants(
            $folderMapper->findByColumnList([
                'owner'  => $sessionUser->getId(),
                'parent' => null,
            ]),
        );
        $this->renderTreeSelect(
            'parent_list',
            'PARENT_',
            $folderTree,
            $folder->getParent()->getId() ?? IFolder::ROOT_ID,
            fn (MyFolderModel $f) => $f->getId(),
            fn (MyFolderModel $f) => $f->getName(),
            fn (MyFolderModel $f) => $f->getChildren(),
        );
    }

    // Assume that you may edit someone else's file
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getOwner()->formatName() . "'s Files",
            'href' => "/my-files/" . $folder->getId(),
            'class' => '',
        ],
        [
            'text' => 'Modify Folder',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else if ($this->arg[0] == 'new') {
    $parentId = $this->arg[1] ?? IFolder::ROOT_ID;
    if (!is_numeric($parentId)) {
        $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
    }
    $this->setFile(TEMPLATE_content, 'cms/myFolder.html');
    $this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


    $this->setTitle('Add Folder');
    $this->setTab('resources');


    if ($parentId) {
        try {
            /** @var Schoolbox\Resource\My\Folder\Model $folder */
            $folder = $folderMapper->findById($parentId);
            // if folder exists and owner is different
            if ((int)$folder->getOwner()->getId() !== (int)$_SESSION['id']) {
                $this->error(ErrorMapper::ERROR_PAGE_NOT_FOUND);
            }
            try {
                $folder->setOwner($userMapper->findById($folder->getOwner()->getId()));
            } catch (\Schoolbox\Mapper\Exception $e) {
                // Has no owner, and that's okay
            }
            $owner = $folder->getOwner();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    } else {
        $owner = $_SESSION['user'];
    }

    $output = array(
        'ACTION' => 'Add',
        'FOLDER_ID' => '',
        'NAME' => '',
        'PARENT_ID' => $parentId,
    );
    $this->setVar($output);


    if (Config::get('webdav_enable')) {
        $this->setBlock(TEMPLATE_content, 'location', 'location_block');
        $this->setVar('location_block', '');
    } else {
        $folderTree = $folderMapper->loadDescendants(
            $folderMapper->findByColumnList([
                'owner'  => $sessionUser->getId(),
                'parent' => null,
            ]),
        );
        $this->renderTreeSelect(
            'parent_list',
            'PARENT_',
            $folderTree,
            (int)$parentId,
            fn (MyFolderModel $f) => $f->getId(),
            fn (MyFolderModel $f) => $f->getName(),
            fn (MyFolderModel $f) => $f->getChildren(),
        );
    }

    // Assume that you may edit someone else's file
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $owner->formatName() . "'s Files",
            'href' => "/my-files/",
            'class' => '',
        ],
        [
            'text' => 'Add Folder',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
}

<?php

use Schoolbox\Breadcrumbs;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\My\Folder\Model as MyFolderModel;

$folderHandler = new MyFolder();
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
//        try {
//            $folder->setOwner($userMapper->findById($folder->getOwner()->getId()));
//        } catch (\Schoolbox\Mapper\Exception $e) {
//            // Has no owner, and that's okay
//        }
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
        renderTreeSelect($this, $folderTree, $folder->getParent()->getId());
//        $parentTree = $folderHandler->getFolderTree(0, 0, $folder->getId());
//        $parentTree = is_array($parentTree) ? $parentTree : array();
//        array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
//        foreach ($parentTree as $parent) {
//            $output = array(
//                'PARENT_ID' => $parent['id'],
//                'PARENT_NAME' => Scrub::text($parent['name']),
//                'PARENT_INDENT' => str_repeat('&nbsp;', ($parent['depth'] + 1) * 4),
//                'PARENT_SELECTED' => $parent['id'] == $folder->getParent() ? 'selected="selected"' : '',
//            );
//            $this->setVar($output);
//            $this->parse('parent_list_block', 'parent_list', true);
//        }
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
    $parentId = $this->arg[1] ?? 0;
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
        renderTreeSelect($this, $folderTree, $parentId);
//        $parentTree = $folderHandler->getFolderTree();
//        $parentTree = is_array($parentTree) ? $parentTree : array();
//        array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
//        foreach ($parentTree as $parent) {
//            $output = array(
//                'PARENT_ID' => $parent['id'],
//                'PARENT_NAME' => Scrub::text($parent['name']),
//                'PARENT_INDENT' => str_repeat('&nbsp;', ($parent['depth'] + 1) * 4),
//                'PARENT_SELECTED' => $parent['id'] == $parentId ? 'selected="selected"' : '',
//            );
//            $this->setVar($output);
//            $this->parse('parent_list_block', 'parent_list', true);
//        }
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

/**
 * Render the <select> element with the tree structure defined,
 * or just a default "top level" if there is none.
 *
 * @param SitePage                         $page       The SitePage object to render into
 * @param GenericCollection<MyFolderModel> $tree       A list of folders to display, each of which may have its own
 *                                                     children (which are rendered recursively)
 * @param int                              $selectedId The currently selected folder
 * @param int                              $depth      The current tree depth: used to indent child folders
 * @return void
 */
function renderTreeSelect(SitePage $page, GenericCollection $tree, int $selectedId = 0, int $depth = 0): void
{
    if ($depth === 0) {
        $output = [
            'PARENT_ID'       => 0,
            'PARENT_NAME'     => 'Top Level',
            'PARENT_INDENT'   => '',
            'PARENT_SELECTED' => $selectedId === 0 ? 'selected="selected"' : '',
        ];
        $page->setVar($output);
        $page->parse('parent_list_block', 'parent_list', true);
    }
    foreach ($tree as $folder) {
        /** @var MyFolderModel $folder */
        $output = [
            'PARENT_ID'       => $folder->getId(),
            'PARENT_NAME'     => Scrub::text($folder->getName()),
            'PARENT_INDENT'   => str_repeat('&nbsp;', ($depth + 1) * 4),
            'PARENT_SELECTED' => $folder->getId() === $selectedId ? 'selected="selected"' : '',
        ];
        $page->setVar($output);
        $page->parse('parent_list_block', 'parent_list', true);
        if ($folder->getChildren()->count() > 0) {
            renderTreeSelect($page, $folder->getChildren(), $selectedId, $depth + 1);
        }
    }
}

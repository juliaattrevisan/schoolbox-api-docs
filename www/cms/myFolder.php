<?php

use \Schoolbox\Breadcrumbs;

$folderHandler = new MyFolder();
$userMapper = new Schoolbox\UserMapper();
$folderMapper = new Schoolbox\Resource\My\Folder\Mapper();

if (is_numeric($this->arg[0]) && $this->arg[0] > 0) {

    try {
        /** @var Schoolbox\Resource\My\Folder\Model $folder */
        $folder = $folderMapper->findById($this->arg[0]);
        try {
            $folder->setOwner($userMapper->findById($folder->getOwner()->getId()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            // Has no owner, and that's okay
        }
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2);
        exit;
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

    if (Cfg::get('webdav_enable')) {
        $this->setBlock(TEMPLATE_content, 'location', 'location_block');
        $this->setVar('location_block', '');

    } else {

        $parentTree = $folderHandler->getFolderTree(0, 0, $folder->getId());
        $parentTree = is_array($parentTree) ? $parentTree : array();
        array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
        foreach ($parentTree as $parent) {
            $output = array(
                'PARENT_ID' => $parent['id'],
                'PARENT_NAME' => $parent['name'],
                'PARENT_INDENT' => str_repeat('&nbsp;', ($parent['depth'] + 1) * 4),
                'PARENT_SELECTED' => $parent['id'] == $folder->getParent() ? 'selected="selected"' : '',
            );
            $this->setVar($output);
            $this->parse('parent_list_block', 'parent_list', true);
        }
    }

    // Assume that you may edit someone else's file
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getOwner()->formatName()."'s Files",
            'href' => "/cms/portfolio/".$folder->getId(),
            'class' => '',
        ],
        [
            'text' => 'Modify Folder',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

    $this->setFile(TEMPLATE_content, 'cms/myFolder.html');
    $this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


    $this->setTitle('Add Folder');
    $this->setTab('resources');


    if ($this->arg[1]) {
        try {
            /** @var Schoolbox\Resource\My\Folder\Model $folder */
            $folder = $folderMapper->findById($this->arg[1]);
            try {
                $folder->setOwner($userMapper->findById($folder->getOwner()->getId()));
            } catch (\Schoolbox\Mapper\Exception $e) {
                // Has no owner, and that's okay
            }
            $owner = $folder->getOwner();
        } catch (\Schoolbox\Mapper\Exception $e) {
            error(2);
            exit;
        }
    } else {
        $owner = $_SESSION['user'];
    }

    $output = array(
        'ACTION' => 'Add',
        'FOLDER_ID' => '',
        'NAME' => '',
        'PARENT_ID' => $this->arg[1],
    );
    $this->setVar($output);


    if (Cfg::get('webdav_enable')) {
        $this->setBlock(TEMPLATE_content, 'location', 'location_block');
        $this->setVar('location_block', '');

    } else {

        $parentTree = $folderHandler->getFolderTree();
        $parentTree = is_array($parentTree) ? $parentTree : array();
        array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
        foreach ($parentTree as $parent) {
            $output = array(
                'PARENT_ID' => $parent['id'],
                'PARENT_NAME' => $parent['name'],
                'PARENT_INDENT' => str_repeat('&nbsp;', ($parent['depth'] + 1) * 4),
                'PARENT_SELECTED' => $parent['id'] == $this->arg[1] ? 'selected="selected"' : '',
            );
            $this->setVar($output);
            $this->parse('parent_list_block', 'parent_list', true);
        }
    }

    // Assume that you may edit someone else's file
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $owner->formatName()."'s Files",
            'href' => "/cms/portfolio/",
            'class' => '',
        ],
        [
            'text' => 'Add Folder',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    $this->error(2);
}

<?php

use \Schoolbox\Breadcrumbs;

$folderHandler = new MyFolder();

if (is_numeric($this->arg[0]) && $this->arg[0] > 0) {

    if (!($folder = $folderHandler->getByID($this->arg[0]))) {
        error(2);
    }

    $this->setFile(TEMPLATE_content, "cms/myFolder.html");
    $this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


    $this->setTitle('Modify Folder');
    $this->setTab('myfiles');

    $output = array(
        'ACTION' => 'Modify',
        'FOLDER_ID' => $folder['id'],
        'NAME' => Scrub::text($folder['name']),
        'PARENT_ID' => $folder['parent'],
    );
    $this->setVar($output);

    if (Cfg::get('webdav_enable')) {
        $this->setBlock(TEMPLATE_content, 'location', 'location_block');
        $this->setVar('location_block', '');

    } else {

        $parentTree = $folderHandler->getFolderTree(0, 0, $folder['id']);
        $parentTree = is_array($parentTree) ? $parentTree : array();
        array_unshift($parentTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
        foreach ($parentTree as $parent) {
            $output = array(
                'PARENT_ID' => $parent['id'],
                'PARENT_NAME' => $parent['name'],
                'PARENT_INDENT' => str_repeat('&nbsp;', ($parent['depth'] + 1) * 4),
                'PARENT_SELECTED' => $parent['id'] == $folder['parent'] ? 'selected="selected"' : '',
            );
            $this->setVar($output);
            $this->parse('parent_list_block', 'parent_list', true);
        }
    }


} else if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

    $this->setFile(TEMPLATE_content, 'cms/myFolder.html');
    $this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


    $this->setTitle('Add Folder');
    $this->setTab('resources');

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

    // Users may currently only create folders for themselves.
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $_SESSION['user']->formatName()."'s Files",
            'href' => "/homepage/",
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

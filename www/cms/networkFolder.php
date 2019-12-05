<?php

use Schoolbox\Breadcrumbs;

if (!Config::get('webdav_enable')) {
    error(2);
}

$folderHandler = PortfolioWebDAV::factory();


if (is_numeric($this->arg[0]) && $this->arg[0] > 0) {

    if (!($folder = $folderHandler->getByID($this->arg[0]))) {
        error(2);
    }

    if ($folderHandler->isRoot($this->arg[0])) {
        error(2);
    }


    $this->setFile(TEMPLATE_content, "cms/networkFolder.html");
    $this->setBlock(TEMPLATE_content, 'parent_list', 'parent_list_block');


    $this->setTitle('Modify Network Folder');
    $this->setTab('mynetwork');

    $output = array(
        'ACTION' => 'Modify',
        'FOLDER_ID' => $folder['id'],
        'NAME' => Scrub::text($folder['name']),
        'PARENT_ID' => $folder['parent'],
    );
    $this->setVar($output);

    $this->setBlock(TEMPLATE_content, 'location', 'location_block');
    $this->setVar('location_block', '');

    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => 'Drives',
            'href' => "/drives/".$folder['parent'],
            'class' => '',
        ],
        [
            'text' => 'Modify Folder',
            'href' => '',
            'class' => 'active',
        ]
    ]);

} else {
    if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

        $this->setFile(TEMPLATE_content, 'cms/networkFolder.html');
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

        $this->setBlock(TEMPLATE_content, 'location', 'location_block');
        $this->setVar('location_block', '');

        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => 'Drives',
                'href' => "/drives/".$this->arg[1],
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
}

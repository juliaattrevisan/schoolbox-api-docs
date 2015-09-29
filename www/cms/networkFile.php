<?php

use Schoolbox\Breadcrumbs;
use Schoolbox\Resource\Attachment\GenericZone;

if (!Cfg::get('webdav_enable')) {
    error(2);
}

$fileHandler = PortfolioWebDAV::factory();
$folderHandler = $fileHandler;

// This goes into storage on its way into webdav.
// Is that okay?
$zone = new GenericZone('cms', Schoolbox\Resource\Storage\Mapper::getInstance());
$zone->setAttachmentsLabel('')
    ->setAttachPrompt('Post file')
    ->setMimeFilter('*/*')
    ->setMulti(false)
    ->setThumbnailSize(64)
    ->setModalTitle('Select file');

if (is_numeric($this->arg[0])) {

    /* Modify existing file */

    $file = $fileHandler->getByID($this->arg[0]);
    if (!is_array($file)) {
        error(41);
    }

    $this->setFile(TEMPLATE_content, 'cms/networkFile.html');
    $this->setFile(TEMPLATE_content, 'cms/networkFile.html');
    $this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
    $this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
    $this->setTitle('Modify Network File');
    $this->setTab('mynetwork');

    $this->setVar('browse_row_block', '');

    $output = array(
        'ACTION' => 'Modify',
        'FILE_ID' => $file['id'],
        'DESCRIPTION' => Scrub::text($file['description']),
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $file['parent'],
    );
    $this->setVar($output);
    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $this->setBlock(TEMPLATE_content, 'location', 'location_block');
    $this->setVar('location_block', '');

    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => "Drives",
            'href'  => "/cms/network/".$file['parent'],
            'class' => '',
        ],
        [
            'text' => 'Modify File',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

    /* Add new file to system */
    if ($this->arg[1] == 0) {
        $parent = array('id' => 0);
    } elseif (!$parent = $folderHandler->getByID($this->arg[1])) {
        error(13);
    }

    $this->setFile(TEMPLATE_content, 'cms/networkFile.html');
    $this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
    $this->setTitle('Add Network File');
    $this->setTab('mynetwork');

    $output = array(
        'ACTION' => 'Post',
        'FILE_ID' => '',
        'DESCRIPTION' => '',
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $parent['id'],
    );

    $this->setVar($output);
    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $this->setBlock(TEMPLATE_content, 'desc', 'desc_block');
    $this->setBlock(TEMPLATE_content, 'location', 'location_block');
    $this->setVar('desc_block', '');
    $this->setVar('location_block', '');

    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => "Drives",
            'href'  => "/cms/network/".$parent['id'],
            'class' => '',
        ],
        [
            'text' => 'Post File',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
else {
    error(2);
}
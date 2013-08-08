<?php

if (!Cfg::get('webdav_enable')) {
    error(2);
}

$fileHandler = PortfolioWebDAV::factory();
$folderHandler = $fileHandler;


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

    $this->setBlock(TEMPLATE_content, 'location', 'location_block');
    $this->setVar('location_block', '');

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
        'ACTION' => 'Add',
        'FILE_ID' => '',
        'DESCRIPTION' => '',
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $parent['id'],
    );

    $this->setVar($output);

    $this->setBlock(TEMPLATE_content, 'desc', 'desc_block');
    $this->setBlock(TEMPLATE_content, 'location', 'location_block');
    $this->setVar('desc_block', '');
    $this->setVar('location_block', '');
}
else {
    error(2);
}
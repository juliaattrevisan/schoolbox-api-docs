<?php

use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\GenericZone;
use \Schoolbox\Resource\File\Mapper as FileMapper;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Breadcrumbs;

$groupHandler = new Group;
$file = $folder = null;

$zone = new GenericZone('cms', FileMapper::getInstance());
$zone->setAttachmentsLabel('')
    ->setAttachPrompt('Post file')
    ->setMimeFilter('*/*')
    ->setMulti(false)
    ->setThumbnailSize(64)
    ->setModalTitle('Select file');

if (is_numeric($this->arg[0])) {

    /* Modify existing file */
    try {
        $file = FileMapper::getInstance()->findById($this->arg[0]);
        $folder = $file->getFolder();
    } catch (Exception $e) {
        error(41);
    }

    if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
        error(6);
    }

    $this->setFile(TEMPLATE_content, 'cms/modify-file.html');
    $this->setBlock(TEMPLATE_content, 'browse', 'browse_block');
    $this->setVar('browse_block', 'The original file posted.');

    $this->setTitle('Modify File');
    $this->setTab('resources');

    $zone->attachFiles([$file]);

    $output = array(
        'ACTION'           => 'Modify',
        'ACTION_DESC'      => 'replace the existing file',
        'FILE_ID'          => $file->getId(),
        'DESCRIPTION'      => Scrub::text($file->getTitle()),
        'COMMENT'          => Scrub::text($file->getDescription()),
        'VISIBLE_SELECTED' => $file->getHidden() > 0 ? '' : 'checked',
        'MAX_FILE_SIZE'    => Cfg::get('max_file_size'),
        'CATEGORY_ID'      => $folder->getId(),
        'CATEGORY_NAME'    => Scrub::text($folder->getName()),
        'DUE_DAY'          => '',
        'DUE_MONTH'        => '',
        'DUE_YEAR'         => '',
        'RETURN_URL'       => $_GET[Redirect::RETURN_URL] ?? '',
    );

    $this->setVar($output);
    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => $folder->getName(),
            'href'  => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text'  => 'Modify File',
            'href'  => '',
            'class' => 'active',
        ]
    ]);
} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

    // Prevent files attached to root
    if (0 == $this->arg[1]) {
        error(6);
    }

    /* Add new file to system */

    $this->setFile(TEMPLATE_content, 'cms/modify-file.html');
    $this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
    $this->setVar('delete_button_block', '');

    $this->setTitle('Add File');
    $this->setTab('resources');

    try {
        $folder = FolderMapper::getInstance()->findById($this->arg[1]);
    } catch (Exception $e) {
        error(13);
    }

    if ($groupHandler->getPermission($folder->getId()) < UserAccess::ACCESS_WRITE) {
        error(6);
    }

    $output = array(
        'ACTION'           => 'Post',
        'ACTION_DESC'      => 'upload',
        'FILE_ID'          => '',
        'DESCRIPTION'      => '',
        'COMMENT'          => '',
        'VISIBLE_SELECTED' => (Cfg::get('file_default_to_hidden') ? '' : 'checked'),
        'MAX_FILE_SIZE'    => Cfg::get('max_file_size'),
        'CATEGORY_NAME'    => Scrub::text($folder->getName()),
        'CATEGORY_ID'      => $folder->getId(),
        'COMPONENT_ID'     => isset($this->arg[2]) ? $this->arg[2] : '',
        'DUE_DAY'          => is_numeric($this->arg[2]) ? $this->arg[2] : '',
        'DUE_MONTH'        => is_numeric($this->arg[3]) ? $this->arg[3] : '',
        'DUE_YEAR'         => is_numeric($this->arg[4]) ? $this->arg[4] : '',
    );

    $this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => $folder->getName(),
            'href'  => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text'  => 'Post File',
            'href'  => '',
            'class' => 'active',
        ]
    ]);
} elseif ($this->arg[0] == 'multicat') {
    /* Upload a file to multiple categories */

    $this->setFile(TEMPLATE_content, 'cms/add-file-multi-cat.html');
    $this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
    $this->setBlock(TEMPLATE_content, 'no_categories', 'no_categories_block');

    $this->setTitle('Upload a File to Multiple Locations');
    $this->setTab('resources');

    $output = array(
        'MAX_FILE_SIZE'    => Cfg::get('max_file_size'),
        'VISIBLE_SELECTED' => (Cfg::get('file_default_to_hidden') ? '' : 'checked'),
    );

    $this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $categoryList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, UserAccess::ACCESS_WRITE);

    if (is_array($categoryList) && count($categoryList)) {
        foreach ($categoryList as $category) {
            $output = array(
                'CATEGORY_NAME' => Scrub::text($category['name']) . ($category['code'] ? ' (' . $category['code'] . ')' : ''),
                'CATEGORY_ID'   => $category['id'],
            );

            $this->setVar($output);

            $this->parse('category_list_block', 'category_list', true);
        }

        $this->setVar('no_categories_block', '');
    } else {
        error(154);
    }

} else {
    error(2);
}

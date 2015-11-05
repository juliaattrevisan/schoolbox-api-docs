<?php

use Alaress\Application\Kernel;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

/** @var Kernel $kernel */
$kernel = require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

/** @var SitePage $page */
$page = $kernel->getDependencyInjector()->get('sitePage');

$page->template->set_file(TEMPLATE_body, dirname(__FILE__) . '/file_browser.html');
$page->setBlock(TEMPLATE_body, 'folder_list', 'folder_list_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');
$page->setBlock('file', 'thumbnail_image', 'thumbnail_image_block');
$page->setBlock('file', 'thumbnail_flash', 'thumbnail_flash_block');

if (!is_numeric($_GET['categoryID'])) {
    die('Invalid category id');
}

$categoryMapper = Folder\Mapper::getInstance();
$fileMapper = Schoolbox\Resource\File\Mapper::getInstance();

$output = array('FILE_CATEGORY_ID'	=> $_GET['categoryID'],
                'REQUEST_URI'		=> $_SERVER['REQUEST_URI'],
                );
$page->setVar($output);

$treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
    Folder\Mapper::getInstance(),
    TaskMapper::getInstance(),
    UserAccessMapper::getInstance(),
    RoleAccessMapper::getInstance(),
    $_SESSION['user'],
    $page->getLocalisedTerm('resource', true, true)
);
$page->setVar('TREEDATA', json_encode($treeBuilder->buildPartialTree($_GET['categoryID'])));

/* output files */
if (in_array($_GET['filter'], array('image', 'video'))) {
    $filter = array('filter' => $_GET['filter']);
    $output = array('FILTER'    => $_GET['filter']);
} else {
    $filter = null;
    $output = array('FILTER'    => '');
}

$fileList = $fileMapper->findForFolders([ $_GET['categoryID'] ], $filter);


$page->setVar($output);

// Is this in a homepage?
try {
    $category = $categoryMapper->findById($_GET['categoryID']);
    $hasHomepage = $category->hasHomepage();
} catch (\Schoolbox\Mapper\Exception $e) {
    $hasHomepage = false;
}
if (is_array($fileList)) {
    // add homepage "file"
    if ($hasHomepage && !is_array($filter)) {
        $homepage = array('id'          => $_GET['categoryID'],
                          'description' => 'Homepage',
                          'size'        => 1,
                          'iconFile'    => 'vcard_fiquet.png',
                          );

        array_unshift($fileList, $homepage);

    }

    foreach ($fileList as $file) {
        $output = array ( 'FILE_ID' 	=> $file['id'],
                          'DESCRIPTION'	=> $file['description'],
                          'TITLE_SHORT' => TextFormatter::cutString($file['description'], 10),
                          'SIZE'  		=> round($file['file_size']/1024),
                          'FILE_ICON'	=> $file['iconFile'],
                          );
        $page->setVar($output);

        $page->setVar('thumbnail_image_block', '');
        $page->setVar('thumbnail_flash_block', '');
        if ($filter['filter'] == 'image') {
            $page->parse('thumbnail_image_block', 'thumbnail_image');
        } else {
            $page->parse('thumbnail_flash_block', 'thumbnail_flash');
        }

        $page->parse('file_block', 'file', true);

    }
} else {
    $page->setVar('file_block', '');
}

$page->outputPage(true);

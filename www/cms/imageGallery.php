<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use \Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;

/* This page generates a photo gallery of the provided folder.
 * Displays one image at large size and the rest as thumbnails.
 * Clicking the thumbnails makes them the larger images.
 * Clicking the large image makes it open in a new window at
 * its original size
 */

/** @var SitePage $this */

if (is_numeric($this->arg[0])) {
    $groupHandler    = new Group;
    $folderMapper = new \Schoolbox\Resource\Folder\Mapper();

    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        error(2);
    }

    if ($groupHandler->getPermission($this->arg[0]) < UserAccess::ACCESS_READ) {
        error(6);
    }

    $this->setFile(TEMPLATE_content, 'cms/image-gallery.html');
    $this->setTitle('Image Gallery for ' . $folder->getName());
    $this->setTab('resources');

    $this->setBlock(TEMPLATE_content, 'image_gallery', 'image_gallery_block');

    $this->setVar('FOLDER_NAME', $folder->getName());
    $this->setVar('CATEGORY_ID', $folder->getId());

    // which homepage do I return to?
    $returnId = $folder->getId();

    $allImages = $folderMapper->findImages($folder);

    if ($allImages->count()) {
        foreach ($allImages as $currentImage) {
            $this->setVar(
                array(
                    'THUMBNAIL_ID'          => $currentImage->getId(),
                    'THUMBNAIL_DESCRIPTION' => $currentImage->getDescription(),
                    'THUMBNAIL_SIZE'        => Config::get('thumbnail_size'),
                )
            );
            $this->parse('image_gallery_block', 'image_gallery', true);
        }
        $this->setVar(
            array(
                'IMAGE_HEIGHT' => Config::get('thumbnail_size'),
            )
        );
    } else {
        $this->setVar('image_gallery_block', '');
    }

    /* Get the homepage from which you came, in case you are sharing
     * this image gallery in another homepage.
     */
    if (isset($_GET['returnId'])) {
        try {
            $returnFolder = $folderMapper->findById((int)$_GET['returnId']);
            if ($groupHandler->getPermission($returnFolder->getId()) >= UserAccess::ACCESS_READ) {
                $folder = $returnFolder;
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            // just use the original folder for breadcrumb, when returnId is invalid
        }
    }

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Image Gallery',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
else {
    error(2);
}

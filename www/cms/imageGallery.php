<?php
/************************************************************
 *                                                          *
 *         Copyright (c) 2013 Alaress Pty Ltd               *
 *                                                          *
 ************************************************************/

/* This page generates a photo gallery of the provided folder.
 * Displays one image at large size and the rest as thumbnails.
 * Clicking the thumbnails makes them the larger images.
 * Clicking the large image makes it open in a new window at
 * its orginal size
 */

if (is_numeric($this->arg[0])) {
    $groupHandler    = new Group;
    $folderMapper = new \Schoolbox\Resource\Folder\Mapper();

    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        error(2);
    }

    if ($groupHandler->getPermission($this->arg[0]) < READ_ACCESS) {
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

    if (isset($this->arg[1])
        && preg_match('/^returnTo:(?P<returnId>\\d+)$/', $this->arg[1], $matches)
    ) {
        $returnId = $matches['returnId'];
    }
    $this->setVar('RETURN_CATEGORY_ID', $returnId);

    $allImages = $folderMapper->findImages($folder);

    if ($allImages->count()) {
        foreach ($allImages as $currentImage) {
            $this->setVar(
                array(
                    'THUMBNAIL_ID'          => $currentImage->getId(),
                    'THUMBNAIL_DESCRIPTION' => $currentImage->getDescription(),
                    'THUMBNAIL_SIZE'        => Cfg::get('thumbnail_size'),
                )
            );
            $this->parse('image_gallery_block', 'image_gallery', true);
        }
        $this->setVar(
            array(
                'IMAGE_HEIGHT' => Cfg::get('thumbnail_size'),
            )
        );
    } else {
        $this->setVar('image_gallery_block', '');
    }
} else {
    error(2);
}

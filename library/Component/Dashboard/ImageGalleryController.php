<?php

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_ImageGalleryController extends Component_Dashboard_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler = null;

    /**
     * Category mapper
     *
     * @var null
     */
    private $categoryMapper = null;

    /**
     * File mapper
     *
     * @var null
     */
    private $fileMapper = null;

    /**
     * Source folder
     *
     * @var null
     */
    private $source = null;

    /**
     * Images per row
     *
     * @var int
     */
    private $imagesPerRow = 4;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->setGroupHandler(new Group());
        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());
        $this->setFileMapper(new \Schoolbox\Resource\FileMapper());
        $this->determineSourceFolder();
    }

    /**
     * Determine source folder for images
     */
    private function determineSourceFolder()
    {

        // remove any non-numeric values from the source config
        $categoryId = $this->getConfig('src') ? preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) : $this->getContext('id');

        try {
            $category = $this->getCategoryMapper()->findById($categoryId);
        } catch (\Exception $e) {
            $category = new \Schoolbox\Resource\Category();
        }

        $this->setSource($category);
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'imageGallery')) {
            return false;
        }

        $this->parseConfig();

        if ($this->getGroupHandler()->getPermission($this->getSource()->getId()) < READ_ACCESS) {
            return false;
        }

        /* Template rendering
         */
        $this->setBlock($templateHandle, 'image_gallery', 'image_gallery_block');

        // Set up component name
        $this->setVar(
            array(
                'COMPONENT_NAME'           => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Gallery',
                'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
            )
        );

        /* Prevent multiple galleries from other categories stacking up in the wrong place
        * and initialise empty gallery by default
        */
        $this->setVar('image_gallery_block', '');

        // Load images for the current homepage
        $allImages = $this->getCategoryMapper()->findImages($this->getSource()->getId(), $this->getConfig('size'), true);

        // Iterate
        foreach ($allImages as $currentImage) {
            $this->setVar(
                array(
                    'THUMBNAIL_ID'          => $currentImage->getId(),
                    'THUMBNAIL_DESCRIPTION' => $currentImage->getDescription(),
                    'THUMBNAIL_SIZE'        => $this->getConfig('pixel') ? $this->getConfig('pixel') : Cfg::get('thumbnail_size'),
                    'IMAGE_HEIGHT'          => $this->getConfig('pixel') ? $this->getConfig('pixel') : Cfg::get('thumbnail_size'),
                )
            );

            $this->parse('image_gallery_block', 'image_gallery', true);
        }

        return true; // everything went according to plan
    }

    /* Getters/Setters
      */

    /**
     * Set category mapper
     *
     * @param null $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return null
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * Set file mapper
     *
     * @param null $fileMapper
     */
    private function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
    }

    /**
     * Get file mapper
     *
     * @return null
     */
    private function getFileMapper()
    {
        return $this->fileMapper;
    }

    /**
     * Set images per row
     *
     * @param int $imagesPerRow
     */
    private function setImagesPerRow($imagesPerRow)
    {
        $this->imagesPerRow = $imagesPerRow;
    }

    /**
     * Get images per row
     *
     * @return int
     */
    private function getImagesPerRow()
    {
        return $this->imagesPerRow;
    }

    /**
     * Set source
     *
     * @param null $source
     */
    private function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * Get source
     *
     * @return null
     */
    private function getSource()
    {
        return $this->source;
    }

    /**
     * Set group handler
     *
     * @param \Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return \Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }
}



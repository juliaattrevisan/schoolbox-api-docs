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
     * A group handler
     *
     * @var Group
     */
    private $groupHandler = null;

    /**
     * A category mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * File mapper
     *
     * @var null
     */
    private $fileMapper = null;

    /**
     * Number of images per row
     *
     * @var int
     */
    private $imagesPerRow = 4;

    /**
     * Source category
     *
     * @var \Schoolbox\Resource\Category
     */
    protected $source = null;

    /**
     * Template file to load
     *
     * @var string
     */
    protected $templateHandle = 'imageGallery';

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
        $categoryId = $this->getConfig('src')
            ? preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src'))
            : $this->getContext()->getId();

        try {
            $this->setSource($this->getCategoryMapper()->findById($categoryId));
        } catch (\Exception $e) {
            $this->setSource(new \Schoolbox\Resource\Category());
        }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        /* TODO: This should be inherited
         */
        if (!parent::loadTemplate($templateHandle, $this->getTemplateHandle())) {
            return false;
        }

        $this->parseConfig();

        if ($this->getGroupHandler()->getPermission($this->getSource()->getId()) < READ_ACCESS) {
            return false;
        }

        /* Template rendering
         */
        $this->setBlock($templateHandle, 'image_gallery', 'image_gallery_block');

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar(array(
            'image_gallery_block' => ''
        ));

        /* Set up component name
         */
        $this->setVar(
            array(
                'COMPONENT_NAME'           => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Gallery',
                'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
            )
        );

        // Load images for the current homepage
        $allImages = $this->getCategoryMapper()->findImages($this->getSource(), $this->getConfig('size'), true);

        // Iterate
        foreach ($allImages as $currentImage) {
            $this->setVar(
                array(
                    'THUMBNAIL_ID'          => $currentImage->getId(),
                    'THUMBNAIL_DESCRIPTION' => $currentImage->getTitle(),
                    'THUMBNAIL_SIZE'        => $this->getConfig('pixel') ? $this->getConfig('pixel') : Cfg::get('thumbnail_size'),
                    'IMAGE_HEIGHT'          => $this->getConfig('pixel') ? $this->getConfig('pixel') : Cfg::get('thumbnail_size'),
                )
            );

            $this->parse('image_gallery_block', 'image_gallery', true);
        }

        // everything went according to plan
        return true;
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

    /**
     * Set source
     *
     * @param null $source
     */
    protected function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * Get source
     *
     * @return null
     */
    protected function getSource()
    {
        return $this->source;
    }

    /**
     * Set template handle
     *
     * @param string $templateHandle
     */
    protected function setTemplateHandle($templateHandle)
    {
        $this->templateHandle = $templateHandle;
    }

    /**
     * Get template handle
     *
     * @return string
     */
    protected function getTemplateHandle()
    {
        return $this->templateHandle;
    }
}

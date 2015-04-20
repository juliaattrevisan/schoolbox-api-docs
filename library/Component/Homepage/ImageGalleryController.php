<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ImageGalleryController extends Component_Homepage_ControllerAbstract
{

    /**
     * A group handler
     *
     * @var Group
     */
    private $groupHandler;

    /**
     * An instance of Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $source;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * Number of images per row
     *
     * @var int
     */
    private $imagesPerRow = 4;

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
        $this->groupHandler = new Group();
        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {

        /* TODO: This should be inherited
         */
        if (!parent::loadTemplate($templateHandle, 'imageGallery')) {
            return false;
        }

        $this->parseConfig();

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        $limit = is_numeric($this->getConfig('size')) ? $this->getConfig('size') * $this->getImagesPerRow() : 0;

        /* Template rendering
         */
        $this->setBlock($templateHandle, 'image_gallery', 'image_gallery_block');
        $this->setBlock($templateHandle, 'submit_image', 'submit_image_block');

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar(
            array(
                'image_gallery_block' => ''
            )
        );

        // Controls such as Post News
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar(
                array(
                    'submit_image_block' => ''
                )
            );
        } else {
            $this->parse('submit_image_block', 'submit_image');
        }

        /* Set up component name
         *
         * Have update form point to the gallery's homepage, not this page (if this is from an alt source)
         */
        $this->setVar(
            array(
                'COMPONENT_NAME'           => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Gallery',
                'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
            )
        );

        // Load images for the requested homepage
        $allImages = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
            [$this->getSource()->getId()],
            [
                'filter' => ['image'],
                'size'   => $limit,
            ]
        );

        // Iterate
        foreach ($allImages as $currentImage) {
            $this->setVar(
                array(
                    'THUMBNAIL_ID'          => $currentImage->getId(),
                    'THUMBNAIL_DESCRIPTION' => $currentImage->getTitle(),
                    'THUMBNAIL_SIZE'        => Cfg::get('thumbnail_size'),
                    'IMAGE_HEIGHT'          => Cfg::get('thumbnail_size'),
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
     * @param \Schoolbox\Resource\CategoryMapper $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return \Schoolbox\Resource\CategoryMapper
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
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

    /**
     * Set source
     *
     * @param \Schoolbox\Resource\Category $source
     */
    protected function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * Get source
     *
     * @return \Schoolbox\Resource\Category
     */
    protected function getSource()
    {
        return $this->source;
    }
}

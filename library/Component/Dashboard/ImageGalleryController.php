<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Permission\Access\UserAccess;

class Component_Dashboard_ImageGalleryController extends Component_Dashboard_ControllerAbstract
{

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
     * Template file to load
     *
     * @var string
     */
    protected $templateHandle = 'imageGallery';

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if(!$this->isSourceRenderable()){
            return false;
        }

        $this->setFileMapper(new \Schoolbox\Resource\File\Mapper());
        return true;
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

        if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
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
                'COMPONENT_NAME'           => strlen($this->getConfig('componentname')) ? Scrub::text($this->getConfig('componentname')) : 'Image Gallery',
                'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
            )
        );


        // Load images for the current homepage
        $allImages = $this->getFolderMapper()->findImages($this->getSource(), $this->getConfig('size'));

        if ($allImages->count()) {
            // Iterate
            foreach ($allImages as $currentImage) {
                $this->setVar(
                    array(
                        'THUMBNAIL_ID' => $currentImage->getId(),
                        'THUMBNAIL_DESCRIPTION' => $currentImage->getTitle(),
                        'THUMBNAIL_SIZE' => $this->getConfig('pixel') ? $this->getConfig('pixel') : Config::get('thumbnail_size'),
                        'IMAGE_HEIGHT' => $this->getConfig('pixel') ? $this->getConfig('pixel') : Config::get('thumbnail_size'),
                    )
                );

                $this->parse('image_gallery_block', 'image_gallery', true);
            }
        } else {
            return false;
        }


        // everything went according to plan
        return true;
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

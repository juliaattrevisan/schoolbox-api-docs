<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\File\Model;

class Component_Homepage_ImageGalleryController extends Component_Homepage_ControllerAbstract
{
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
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }
        return true;
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
        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_images', 'has_images_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        // Load images for the requested homepage
        $allImages = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
            [$this->getSource()->getId()],
            [
                'filter' => ['image'],
                'size' => $this->getConfig('sizeNoAll')
            ]
        );

        // Sort in a human way
        $allImages = $allImages->sortByCallback( function (Model $a, Model $b) {return strnatcasecmp($a->getTitle(), $b->getTitle());} );

        $this->setVar([
            'COMPONENT_NAME' => strlen($this->getConfig('componentname')) ? $this->getConfig('componentname') : $this->getComponentName(),
            'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
        ]);

        if ($allImages->count() == 0) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_images_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            /* Template rendering
             */
            $this->setBlock('has_images', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_images', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'image_gallery', 'image_gallery_block');
            $this->setBlock('source_folder_exists', 'submit_image_gallery', 'submit_image_gallery_block');

            $this->setVar([
                'empty_state_block' => '',
                'source_folder_deleted_block' => '',
                'image_gallery_block' => '',
                'submit_image_gallery_block' => '',
            ]);

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {

                /* Set up component name
                 *
                 * Have update form point to the gallery's homepage, not this page (if this is from an alt source)
                 */

                // Controls such as Post News
                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    $this->parse('submit_image_gallery_block', 'submit_image_gallery');
                }

                // Iterate
                foreach ($allImages as $currentImage) {
                    $this->setVar([
                        'THUMBNAIL_ID' => $currentImage->getId(),
                        'THUMBNAIL_DESCRIPTION' => $currentImage->getTitle(),
                        'THUMBNAIL_SIZE' => Config::get('thumbnail_size'),
                        'IMAGE_HEIGHT' => Config::get('thumbnail_size'),
                    ]);

                    $this->parse('image_gallery_block', 'image_gallery', true);
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_images_block', 'has_images');
        }

        // everything went according to plan
        return true;
    }

    /* Getters/Setters
     */

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
